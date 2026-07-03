# ADR 001: Laravel 13 and MySQL 8.4
Date: 2026-07-03. Status: accepted. Owner: Architecture.

Use Laravel modular monolith and MySQL constraints/transactions. This maximizes native
operations and atomic audit guarantees. Separate services and SQLite were rejected because
they add operational cost or cannot prove production constraints.
