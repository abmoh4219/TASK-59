# Workforce & Operations Hub

## Run
```bash
docker compose up --build
```
Frontend: http://localhost:3000
API: http://localhost:8000

## Test
```bash
docker compose --profile test run --build test
```

## Stop
```bash
docker compose down
```

## Login

> **Intentional QA credentials — evaluation use only.**
> The credentials below are published here **on purpose** so the QA reviewer
> can log in as every role and exercise the full application during the
> runtime evaluation. They are seed accounts loaded by the Doctrine fixtures
> and are **not** production credentials. Do not reuse them outside local
> evaluation; rotate before any non-local deployment.

| Role | Username | Password |
|---|---|---|
| System Administrator | admin | Admin@WFOps2024! |
| HR Admin | hradmin | HRAdmin@2024! |
| Supervisor | supervisor | Super@2024! |
| Employee | employee | Emp@2024! |
| Dispatcher | dispatcher | Dispatch@2024! |
| Technician | technician | Tech@2024! |
