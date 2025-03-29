# Benchmark for html_entity_decode optimization

---

This branch includes a benchmark to test the optimization of the `html_entity_decode` function. The previous version is still available as `html_entity_decode_old`. The benchmark code is in `html_entity_decode_benchmark.php`.

Just build PHP as usual and run:

```bash
./sapi/cli/php html_entity_decode_benchmark.php
```

```
------------------------------------------------------------------------------
|                      Test |     old avg(ns) |     new avg(ns) |    diff(%) |
------------------------------------------------------------------------------
|                      4k & |            5949 |           21115 |    -71.98% |
------------------------------------------------------------------------------
|             only entities |            8279 |           10202 |    -18.80% |
------------------------------------------------------------------------------
|        400 valid entities |            6439 |            5861 |      7.80% |
------------------------------------------------------------------------------
|        200 valid entities |            4891 |            3178 |     38.12% |
------------------------------------------------------------------------------
|        200 invalid entity |            4777 |            3181 |     37.29% |
------------------------------------------------------------------------------
|             200 ampersand |            4809 |            1221 |    198.35% |
------------------------------------------------------------------------------
|        100 valid entities |            4188 |            1777 |    124.49% |
------------------------------------------------------------------------------
|         50 valid entities |            2885 |             979 |    193.50% |
------------------------------------------------------------------------------
|        String ends with & |            2428 |             176 |   1221.69% |
------------------------------------------------------------------------------

```

As you can see, the speedup depends on the number of entities and `&` characters in the string — the fewer there are, the more noticeable the performance improvement.

In edge cases, where the string consists entirely of `&` characters or valid HTML entities, performance actually worsens. However, I don't think this is a common scenario.

Either way, I plan to continue optimizing and implement `&` scanning using SIMD instructions, which should significantly improve performance even in high-entity-density cases.
