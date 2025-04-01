# Benchmark for `htmlspecialchars` optimization

---

This branch includes a benchmark to test the optimization of the `htmlspecialchars` function. The previous version is still available as `htmlspecialchars_old`. The benchmark code is in `benchmark.php`.

Just build PHP with `mbstring` and run:

```bash
./sapi/cli/php benchmark.php
```

```
----------------------------------------------------------------------------------------
|                                Test |     old avg(ns) |     new avg(ns) |    diff(%) |
----------------------------------------------------------------------------------------
|                        Empty string |              63 |              42 |     50.00% |
----------------------------------------------------------------------------------------
|                              1 char |              64 |              78 |    -17.95% |
----------------------------------------------------------------------------------------
|                              4 char |              76 |              81 |     -6.17% |
----------------------------------------------------------------------------------------
|                              8 char |              93 |              86 |      8.14% |
----------------------------------------------------------------------------------------
|                     1000 spec. char |           14257 |            8449 |     68.74% |
----------------------------------------------------------------------------------------
|                       ASCII letters |            9647 |            3293 |    192.95% |
----------------------------------------------------------------------------------------
|                          Emoji UTF8 |           19212 |           14991 |     28.16% |
----------------------------------------------------------------------------------------
|                       Cyrillic UTF8 |           17028 |           11767 |     44.71% |
----------------------------------------------------------------------------------------
|                        Chinese UTF8 |           18220 |           14904 |     22.25% |
----------------------------------------------------------------------------------------
|                          Japan UTF8 |           18223 |           14880 |     22.47% |
----------------------------------------------------------------------------------------
|                     Cyrillic CP1251 |            9664 |            3858 |    150.49% |
----------------------------------------------------------------------------------------
|                        Chinese Big5 |           27433 |           24126 |     13.71% |
----------------------------------------------------------------------------------------
|                          Japan SJIS |           16125 |           16090 |      0.22% |
----------------------------------------------------------------------------------------
|         200 entities !double_decode |           12979 |            7499 |     73.08% |
----------------------------------------------------------------------------------------
|         800 entities !double_decode |           10363 |            9454 |      9.61% |
----------------------------------------------------------------------------------------

```
The main performance improvement comes from fast-path handling of ASCII bytes and single-byte encodings using a lookup table for special character detection.

The new validate_utf8_char function efficiently handles multi-byte UTF-8 characters.

Overall, the logic for character processing and validation has been improved.

Performance is lower for single-character strings due to the overhead of initializing the LUT.

In the benchmark, I tried to cover a variety of scenarios with different encodings and flags, but feel free to run it on your own data and share the results.
