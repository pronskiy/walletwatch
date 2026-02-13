Dashboard is completely unusable for power users. I seeded 10k transactions and the page takes 8+ seconds to load. The monthly summary widget is the worst offender — it's loading ALL transactions and doing math in PHP instead of SQL. Also I think there's N+1 queries on the account list because each one loads its transactions separately.

Please fix. This is embarrassing.