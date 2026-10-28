PHP_ARG_ENABLE([strmatch],
  [whether to enable strmatch support],
  [AS_HELP_STRING([--enable-strmatch],
    [Enable multi-pattern string matching (Str\Matcher / Str\Replacer)])],
  [no])

if test "$PHP_STRMATCH" != "no"; then
  AC_DEFINE(HAVE_STRMATCH, 1, [ Whether strmatch is enabled ])
  PHP_NEW_EXTENSION(strmatch, strmatch.c, $ext_shared)
fi
