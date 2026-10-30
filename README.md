# TorqueERP

Multi-branch ERP, POS, inventory, finance, public marketing website, online store/reservations, HR/payroll, service/workshop and reporting system.

Built for Taziva Motor Spares (TMS), Rusape, Zimbabwe, with configurable terminology/catalogues for other trades.

## Delivery status
Phase 0: DONE
- 0.1 Audit and plan: DONE
- 0.2 Architecture decisions: DONE
- 0.3 Development foundation: DONE
- 0.4 Schema and migration baseline: DONE (MariaDB tested)
- 0.5 Auth and tenant skeleton: DONE
- 0.6 App shell, UI system and language: DONE
- 0.7 Live skeleton acceptance: DONE

Phase 1
- 1.1 Users, roles and permissions: DONE
- 1.2 Products, categories, units and stock foundation: DONE (SQLite and MariaDB tested)
- 1.3 Canonical stock ledger: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.4 Customer records: DONE (MariaDB tested; SQLite blocked by missing pdo_sqlite driver)
- 1.5+ Not started (owner approval required)

## Quick start (development)
See `docs/DEVELOPMENT.md` for setup, run, test, lint/format/type commands.

## Local URLs (verified)
- Frontend dev: `http://localhost:5173/` (Vite)
- Backend API: `http://localhost:8000/api/` (Laravel)
- Health: `http://localhost:8000/api/health`
- Routes: `/`, `/login`, `/app`, `/app/products`, `/app/users`, `/platform`

## Tech stack
- Backend: Laravel 13 + MariaDB
- Frontend: React 18 + TypeScript + Vite + Tailwind CSS + shadcn/ui patterns
- Auth: Laravel Breeze API + Sanctum (public registration disabled)
- Data: TanStack Query, typed API client ready
