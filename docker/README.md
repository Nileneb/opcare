# OPCare — Docker-Stack

Ein self-contained Stack. **Eine** `.env`, ein Befehl:

```bash
cp .env.example .env
docker compose up --build
```

App läuft danach auf **http://localhost:8099** (Port via `APP_PORT` in der `.env`).
Login: `admin@opcare.local` / `password` (Demo-Seed, nur bei frischer DB).

Der `app`-Container erzeugt den `APP_KEY` (falls leer), migriert und seedet bei
frischer Datenbank automatisch. Code + Vite-Assets sind ins Image gebacken — es
werden keine Host-Bind-Mounts gebraucht, nur die `.env` wird hineingemountet.

## Services

| Service     | Zweck                                  | Host-Port |
|-------------|----------------------------------------|-----------|
| `web`       | nginx (statisch + FastCGI → app)       | `${APP_PORT}` (8099) |
| `app`       | PHP-FPM (Laravel), macht Migrate/Seed  | –         |
| `horizon`   | Queue-Worker (Redis)                   | –         |
| `reverb`    | WebSocket-Server (Broadcasting)        | `${REVERB_PORT}` (8080) |
| `scheduler` | `schedule:work` (z. B. MaterializeSchedulesJob) | – |
| `postgres`  | PostgreSQL 16                          | – (nur intern) |
| `redis`     | Redis 7 (Queue/Cache/Session)          | – (nur intern) |

## Häufige Befehle

```bash
docker compose up -d --build        # im Hintergrund starten/bauen
docker compose logs -f app          # Logs des App-Containers
docker compose exec app php artisan ...   # Artisan im Container
docker compose exec postgres psql -U opcare   # direkter DB-Zugriff
docker compose run --rm test        # Pest-Suite (Profil "test", SQLite)
docker compose down                 # stoppen
docker compose down -v              # stoppen + Daten-Volumes löschen (frische DB)
```

Es gibt bewusst **keine** `.env.dev`/`.env.prod` und kein `docker-compose.override.yml`
— dieselbe `.env` + `docker-compose.yml` treiben den ganzen Stack.
