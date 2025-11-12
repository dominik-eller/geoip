# Beispiele

Dieses Verzeichnis enthält kleine, lauffähige Beispiele zur Nutzung des Pakets.

- update.php – Lädt/aktualisiert die GeoTargets CSV in ./data
- lookup.php – Führt ein Lookup anhand einer Kriterien-ID (criteria_id / loc_physical_ms) aus
- web-endpoint.php – Minimaler HTTP‑Endpunkt, der `loc`/`loc_physical_ms` akzeptiert und JSON zurückgibt

Ausführen (im Projektordner):

```
php examples/update.php
php examples/lookup.php 2267
php -S 127.0.0.1:8080 -t examples
# dann im Browser: http://127.0.0.1:8080/web-endpoint.php?loc=2267
```
