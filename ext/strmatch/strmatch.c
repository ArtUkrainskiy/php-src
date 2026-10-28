/* strmatch: prototype of Str\Matcher — Aho-Corasick multi-string search.
 * Automaton and keyword storage are built once in the constructor and freed in
 * free_obj; the object is reused for many subjects. Results are emalloc'd per call.
 *
 * Memory: the goto table is alphabet-reduced. Only byte values that occur in the
 * keywords get a column (1..K); every other byte maps to column 0, whose transition is
 * root (0) for every node — the fallback. So the table is n*(K+1) ints, not n*256.
 *
 * Query API:
 *   matches(string): bool               stop on first occurrence
 *   countMatches(string): int           count all occurrences (overlapping), no alloc
 *   getMatchedKeywords(string): array   unique keywords, first-occurrence order
 *   matchAll(string): array             list<Str\MatchResult> by offset
 *   getKeywords(): array                normalized keyword list
 */
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif
#include <stddef.h>
#include "php.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_interfaces.h"
#include "Zend/zend_smart_str.h"
#include "php_strmatch.h"
#include "strmatch_arginfo.h"

/* ---- Aho-Corasick, alphabet-reduced flat goto table ---- */
typedef struct { int fail; int wordid; int out_link; } acmeta;

typedef struct {
    int       *trans;        /* flat goto table, n rows of asize columns; col 0 = "other" -> root */
    acmeta    *meta;         /* per-node fail / wordid / out_link, n entries */
    int        n, cap;
    int        asize;        /* columns = distinct keyword bytes (K) + 1 */
    unsigned char amap[256]; /* byte -> column; 0 for any byte absent from all keywords */
    char     **words;        /* keyword byte copies, for results */
    size_t    *wlen;
    char     **repl;         /* replacement per wordid (Str\Replacer only), else NULL */
    size_t    *rlen;
    int        nwords;
    zend_object std;
} matcher_obj;

static zend_class_entry *str_matcher_ce;
static zend_class_entry *str_replacer_ce;
static zend_class_entry *str_matchresult_ce;
static zend_object_handlers matcher_handlers;
/* cached property slot offsets of Str\MatchResult, resolved once in MINIT, so matchAll
   writes slots directly instead of hashing the property name per result */
static uint32_t mr_off_offset, mr_off_length, mr_off_keyword;

static inline matcher_obj *matcher_from(zend_object *o) {
    return (matcher_obj *)((char *)o - offsetof(matcher_obj, std));
}

/* one goto step: column for the byte (0 if the byte is in no keyword) */
static zend_always_inline int ac_goto(const matcher_obj *m, int s, unsigned char c) {
    return m->trans[(size_t)s * m->asize + m->amap[c]];
}

static int ac_newnode(matcher_obj *m) {
    if (m->n == m->cap) {
        m->cap = m->cap ? m->cap * 2 : 256;
        m->trans = perealloc(m->trans, (size_t)m->cap * m->asize * sizeof(int), 1);
        m->meta  = perealloc(m->meta, (size_t)m->cap * sizeof(acmeta), 1);
    }
    int *row = &m->trans[(size_t)m->n * m->asize];
    for (int i = 0; i < m->asize; i++) row[i] = -1;
    m->meta[m->n].fail = 0; m->meta[m->n].wordid = -1; m->meta[m->n].out_link = -1;
    return m->n++;
}

static void ac_build(matcher_obj *m) {
    /* alphabet: distinct keyword bytes -> columns 1..K; column 0 = everything else.
       A keyword byte is always in the alphabet, so column 0 never gets a trie edge;
       the BFS below fills column 0 with root (0) at every node, which is the fallback. */
    for (int i = 0; i < 256; i++) m->amap[i] = 0;
    int K = 0;
    for (int w = 0; w < m->nwords; w++)
        for (size_t i = 0; i < m->wlen[w]; i++) {
            unsigned char c = (unsigned char)m->words[w][i];
            if (m->amap[c] == 0) m->amap[c] = (unsigned char)(++K);
        }
    m->asize = K + 1;

    m->trans = NULL; m->meta = NULL; m->n = 0; m->cap = 0;
    ac_newnode(m); /* root = 0 */
    for (int w = 0; w < m->nwords; w++) {
        int s = 0;
        for (size_t i = 0; i < m->wlen[w]; i++) {
            int col = m->amap[(unsigned char)m->words[w][i]];
            int cur = m->trans[(size_t)s * m->asize + col];
            if (cur < 0) { int nn = ac_newnode(m); m->trans[(size_t)s * m->asize + col] = nn; cur = nn; }
            s = cur;
        }
        m->meta[s].wordid = w;
    }
    int *queue = emalloc(m->n * sizeof(int)); int qh = 0, qt = 0; /* transient */
    for (int col = 0; col < m->asize; col++) {
        int v = m->trans[col]; /* row 0 (root) */
        if (v < 0) m->trans[col] = 0;
        else { m->meta[v].fail = 0; queue[qt++] = v; }
    }
    while (qh < qt) {
        int u = queue[qh++], f = m->meta[u].fail;
        m->meta[u].out_link = (m->meta[f].wordid >= 0) ? f : m->meta[f].out_link;
        for (int col = 0; col < m->asize; col++) {
            int v = m->trans[(size_t)u * m->asize + col];
            if (v < 0) m->trans[(size_t)u * m->asize + col] = m->trans[(size_t)f * m->asize + col];
            else { m->meta[v].fail = m->trans[(size_t)f * m->asize + col]; queue[qt++] = v; }
        }
    }
    efree(queue);
}

/* ---- object lifecycle ---- */
static zend_object *matcher_create(zend_class_entry *ce) {
    matcher_obj *m = zend_object_alloc(sizeof(matcher_obj), ce);
    m->trans = NULL; m->meta = NULL; m->n = 0; m->cap = 0; m->asize = 0;
    m->words = NULL; m->wlen = NULL; m->repl = NULL; m->rlen = NULL; m->nwords = 0;
    zend_object_std_init(&m->std, ce);
    object_properties_init(&m->std, ce);
    m->std.handlers = &matcher_handlers;
    return &m->std;
}

/* free the C-owned automaton and keyword storage, leaving the zend_object intact.
 * Used by the destructor and at the start of __construct, so re-constructing an
 * instance does not leak the previous build. */
static void matcher_reset(matcher_obj *m) {
    pefree(m->trans, 1);
    pefree(m->meta, 1);
    if (m->words) {
        for (int i = 0; i < m->nwords; i++) pefree(m->words[i], 1);
        pefree(m->words, 1);
        pefree(m->wlen, 1);
    }
    if (m->repl) {
        for (int i = 0; i < m->nwords; i++) pefree(m->repl[i], 1);
        pefree(m->repl, 1);
        pefree(m->rlen, 1);
    }
    m->trans = NULL; m->meta = NULL; m->n = 0; m->cap = 0; m->asize = 0;
    m->words = NULL; m->wlen = NULL; m->repl = NULL; m->rlen = NULL; m->nwords = 0;
}

static void matcher_free(zend_object *o) {
    matcher_obj *m = matcher_from(o);
    matcher_reset(m);
    zend_object_std_dtor(&m->std);
}

/* ---- methods (arginfo + method tables generated into strmatch_arginfo.h) ---- */
PHP_METHOD(Str_Matcher, __construct) {
    HashTable *kw;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY_HT(kw)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    matcher_reset(m); /* idempotent: a second __construct must not leak the first */

    uint32_t n = zend_hash_num_elements(kw);
    m->words = n ? pemalloc(n * sizeof(char *), 1) : NULL;
    m->wlen  = n ? pemalloc(n * sizeof(size_t), 1) : NULL;
    m->nwords = 0;

    /* drop duplicate keywords so getKeywords() is a normalized set */
    HashTable seen;
    zend_hash_init(&seen, n, NULL, NULL, 0);

    zval *entry;
    ZEND_HASH_FOREACH_VAL(kw, entry) {
        if (Z_TYPE_P(entry) != IS_STRING) {
            zend_hash_destroy(&seen);
            zend_argument_type_error(1, "must be an array of strings");
            RETURN_THROWS();
        }
        if (Z_STRLEN_P(entry) == 0) {
            zend_hash_destroy(&seen);
            zend_argument_value_error(1, "must not contain an empty keyword");
            RETURN_THROWS();
        }
        if (!zend_hash_add_empty_element(&seen, Z_STR_P(entry))) continue; /* duplicate */
        size_t len = Z_STRLEN_P(entry);
        m->words[m->nwords] = pemalloc(len, 1);
        memcpy(m->words[m->nwords], Z_STRVAL_P(entry), len);
        m->wlen[m->nwords] = len;
        m->nwords++;
    } ZEND_HASH_FOREACH_END();

    zend_hash_destroy(&seen);
    ac_build(m);
}

/* matches(string $subject): bool  — true on the first occurrence, no allocation */
PHP_METHOD(Str_Matcher, matches) {
    zend_string *text;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(text)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    if (m->nwords == 0) RETURN_FALSE;
    const char *t = ZSTR_VAL(text);
    size_t tlen = ZSTR_LEN(text);
    int s = 0;
    for (size_t i = 0; i < tlen; i++) {
        s = ac_goto(m, s, (unsigned char)t[i]);
        if (m->meta[s].wordid >= 0 || m->meta[s].out_link >= 0) RETURN_TRUE;
    }
    RETURN_FALSE;
}

/* countMatches(string $subject): int — all occurrences (overlapping), no allocation */
PHP_METHOD(Str_Matcher, countMatches) {
    zend_string *text;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(text)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    zend_long count = 0;
    if (m->nwords == 0) RETURN_LONG(0);
    const char *t = ZSTR_VAL(text);
    size_t tlen = ZSTR_LEN(text);
    int s = 0;
    for (size_t i = 0; i < tlen; i++) {
        s = ac_goto(m, s, (unsigned char)t[i]);
        int o = s;
        if (m->meta[o].wordid < 0) o = m->meta[o].out_link;
        while (o >= 0) { count++; o = m->meta[o].out_link; }
    }
    RETURN_LONG(count);
}

/* getMatchedKeywords(string $subject): array — unique keywords, first-occurrence order */
PHP_METHOD(Str_Matcher, getMatchedKeywords) {
    zend_string *text;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(text)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    array_init(return_value);
    if (m->nwords == 0 || ZSTR_LEN(text) == 0) return;

    char *seen = ecalloc(m->nwords, 1);
    const char *t = ZSTR_VAL(text);
    size_t tlen = ZSTR_LEN(text);
    int s = 0;
    for (size_t i = 0; i < tlen; i++) {
        s = ac_goto(m, s, (unsigned char)t[i]);
        int o = s;
        if (m->meta[o].wordid < 0) o = m->meta[o].out_link;
        while (o >= 0) {
            int w = m->meta[o].wordid;
            if (!seen[w]) {
                seen[w] = 1;
                add_next_index_str(return_value, zend_string_init(m->words[w], m->wlen[w], 0));
            }
            o = m->meta[o].out_link;
        }
    }
    efree(seen);
}

/* raw occurrence, collected before the result objects are built */
typedef struct { zend_long off; zend_long len; int wid; } ac_hit;

/* sort by ascending offset, ties by ascending length */
static int hit_cmp(const void *a, const void *b) {
    const ac_hit *x = a, *y = b;
    if (x->off != y->off) return x->off < y->off ? -1 : 1;
    if (x->len != y->len) return x->len < y->len ? -1 : 1;
    return 0;
}

/* matchAll(string $subject): array — list<Str\MatchResult>, ascending offset.
 * AC emits at the pattern END position, so a longer keyword that ends later can
 * start earlier than an already-emitted shorter one. Collect then sort for a
 * left-to-right contract. */
PHP_METHOD(Str_Matcher, matchAll) {
    zend_string *text;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(text)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    array_init(return_value);
    if (m->nwords == 0 || ZSTR_LEN(text) == 0) return;

    const char *t = ZSTR_VAL(text);
    size_t tlen = ZSTR_LEN(text);
    ac_hit *hits = NULL;
    size_t nhits = 0, cap = 0;
    int s = 0;
    for (size_t i = 0; i < tlen; i++) {
        s = ac_goto(m, s, (unsigned char)t[i]);
        int o = s;
        if (m->meta[o].wordid < 0) o = m->meta[o].out_link;
        while (o >= 0) {
            int w = m->meta[o].wordid;
            if (nhits == cap) { cap = cap ? cap * 2 : 64; hits = erealloc(hits, cap * sizeof(ac_hit)); }
            hits[nhits].off = (zend_long)(i - m->wlen[w] + 1);
            hits[nhits].len = (zend_long)m->wlen[w];
            hits[nhits].wid = w;
            nhits++;
            o = m->meta[o].out_link;
        }
    }
    if (nhits == 0) return;
    qsort(hits, nhits, sizeof(ac_hit), hit_cmp);
    for (size_t k = 0; k < nhits; k++) {
        int w = hits[k].wid;
        zval res;
        object_init_ex(&res, str_matchresult_ce);
        zend_object *obj = Z_OBJ(res);
        /* typed properties start as UNDEF (no default), so writing the slot needs no dtor */
        ZVAL_LONG(OBJ_PROP(obj, mr_off_offset), hits[k].off);
        ZVAL_LONG(OBJ_PROP(obj, mr_off_length), hits[k].len);
        ZVAL_STR(OBJ_PROP(obj, mr_off_keyword), zend_string_init(m->words[w], m->wlen[w], 0));
        add_next_index_zval(return_value, &res);
    }
    efree(hits);
}

/* getKeywords(): array — the normalized keyword list */
PHP_METHOD(Str_Matcher, getKeywords) {
    ZEND_PARSE_PARAMETERS_NONE();
    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    array_init_size(return_value, m->nwords);
    for (int w = 0; w < m->nwords; w++) {
        add_next_index_str(return_value, zend_string_init(m->words[w], m->wlen[w], 0));
    }
}

/* ---- Str\Replacer extends Str\Matcher ---- */
/* __construct(array $replacements): keys -> keywords, values -> replacements */
PHP_METHOD(Str_Replacer, __construct) {
    HashTable *rep;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ARRAY_HT(rep)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    matcher_reset(m); /* idempotent: a second __construct must not leak the first */

    uint32_t n = zend_hash_num_elements(rep);
    m->words = n ? pemalloc(n * sizeof(char *), 1) : NULL;
    m->wlen  = n ? pemalloc(n * sizeof(size_t), 1) : NULL;
    m->repl  = n ? pemalloc(n * sizeof(char *), 1) : NULL;
    m->rlen  = n ? pemalloc(n * sizeof(size_t), 1) : NULL;
    m->nwords = 0;

    /* array keys are already unique, so no dedup needed here */
    zend_string *key; zend_ulong idx; zval *val;
    ZEND_HASH_FOREACH_KEY_VAL(rep, idx, key, val) {
        if (Z_TYPE_P(val) != IS_STRING) {
            zend_argument_type_error(1, "must be an array of string replacements");
            RETURN_THROWS();
        }
        /* keyword from the key; integer keys are stringified like PHP arrays do */
        char ibuf[32]; const char *kstr; size_t klen;
        if (key) { kstr = ZSTR_VAL(key); klen = ZSTR_LEN(key); }
        else { klen = snprintf(ibuf, sizeof ibuf, ZEND_ULONG_FMT, idx); kstr = ibuf; }
        if (klen == 0) {
            zend_argument_value_error(1, "must not contain an empty keyword");
            RETURN_THROWS();
        }

        m->words[m->nwords] = pemalloc(klen, 1);
        memcpy(m->words[m->nwords], kstr, klen);
        m->wlen[m->nwords] = klen;

        size_t vlen = Z_STRLEN_P(val);
        m->repl[m->nwords] = pemalloc(vlen ? vlen : 1, 1);
        memcpy(m->repl[m->nwords], Z_STRVAL_P(val), vlen);
        m->rlen[m->nwords] = vlen;
        m->nwords++;
    } ZEND_HASH_FOREACH_END();

    ac_build(m);
}

/* replace(string $subject, ?int &$count = null): string
 * strtr semantics: scan left to right, at each position take the LONGEST keyword
 * starting there, emit its replacement, continue past it (non-overlapping, the
 * inserted text is not rescanned). */
PHP_METHOD(Str_Replacer, replace) {
    zend_string *subject;
    zval *zcount = NULL;
    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_STR(subject)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL(zcount)
    ZEND_PARSE_PARAMETERS_END();

    matcher_obj *m = matcher_from(Z_OBJ_P(ZEND_THIS));
    size_t tlen = ZSTR_LEN(subject);
    if (m->nwords == 0 || tlen == 0) {
        if (zcount) ZEND_TRY_ASSIGN_REF_LONG(zcount, 0);
        RETURN_STR_COPY(subject);
    }
    const char *t = ZSTR_VAL(subject);

    /* best[start] = wordid of the longest keyword occurring at this start, or -1 */
    int *best = emalloc(tlen * sizeof(int));
    memset(best, 0xff, tlen * sizeof(int)); /* -1 */
    int s = 0;
    for (size_t i = 0; i < tlen; i++) {
        s = ac_goto(m, s, (unsigned char)t[i]);
        int o = s;
        if (m->meta[o].wordid < 0) o = m->meta[o].out_link;
        while (o >= 0) {
            int w = m->meta[o].wordid;
            size_t start = i - m->wlen[w] + 1;
            if (best[start] < 0 || m->wlen[w] > m->wlen[best[start]]) best[start] = w;
            o = m->meta[o].out_link;
        }
    }

    smart_str out = {0};
    zend_long count = 0;
    size_t pos = 0, lit = 0; /* lit = start of the pending literal run */
    while (pos < tlen) {
        int w = best[pos];
        if (w >= 0) {
            if (pos > lit) smart_str_appendl(&out, t + lit, pos - lit);
            smart_str_appendl(&out, m->repl[w], m->rlen[w]);
            pos += m->wlen[w];
            lit = pos;
            count++;
        } else {
            pos++;
        }
    }
    if (tlen > lit) smart_str_appendl(&out, t + lit, tlen - lit);
    efree(best);

    if (zcount) ZEND_TRY_ASSIGN_REF_LONG(zcount, count);
    if (!out.s) RETURN_EMPTY_STRING();
    smart_str_0(&out);
    RETURN_STR(out.s);
}

PHP_MINIT_FUNCTION(strmatch) {
    str_matchresult_ce = register_class_Str_MatchResult();
    mr_off_offset  = ((zend_property_info *)zend_hash_str_find_ptr(&str_matchresult_ce->properties_info, "offset",  sizeof("offset")-1))->offset;
    mr_off_length  = ((zend_property_info *)zend_hash_str_find_ptr(&str_matchresult_ce->properties_info, "length",  sizeof("length")-1))->offset;
    mr_off_keyword = ((zend_property_info *)zend_hash_str_find_ptr(&str_matchresult_ce->properties_info, "keyword", sizeof("keyword")-1))->offset;

    /* Matcher is not final (Str\Replacer extends it); immutability is from having
       no mutators, not from the final flag. */
    str_matcher_ce = register_class_Str_Matcher();
    str_matcher_ce->create_object = matcher_create;
    /* Automaton state is not plain properties: default (un)serialize would silently
       produce an empty automaton. Forbid it until a real __serialize is added. */
    str_matcher_ce->ce_flags |= ZEND_ACC_NOT_SERIALIZABLE;

    memcpy(&matcher_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
    matcher_handlers.offset = offsetof(matcher_obj, std);
    matcher_handlers.free_obj = matcher_free;
    /* NULL clone_obj makes the engine reject clone (default clone would skip
       create_object and leave the C fields unallocated). */
    matcher_handlers.clone_obj = NULL;

    /* Str\Replacer inherits Matcher's methods, create_object and object layout */
    str_replacer_ce = register_class_Str_Replacer(str_matcher_ce);
    str_replacer_ce->ce_flags |= ZEND_ACC_NOT_SERIALIZABLE;

    return SUCCESS;
}

zend_module_entry strmatch_module_entry = {
    STANDARD_MODULE_HEADER,
    "strmatch", NULL, PHP_MINIT(strmatch), NULL, NULL, NULL, NULL,
    PHP_STRMATCH_VERSION, STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_STRMATCH
ZEND_GET_MODULE(strmatch)
#endif
