/* strmatch: multi-pattern string search and replace (Aho-Corasick). */
#ifndef PHP_STRMATCH_H
#define PHP_STRMATCH_H

#include "php.h"

extern zend_module_entry strmatch_module_entry;
#define phpext_strmatch_ptr &strmatch_module_entry

#define PHP_STRMATCH_VERSION "0.5"

#endif /* PHP_STRMATCH_H */
