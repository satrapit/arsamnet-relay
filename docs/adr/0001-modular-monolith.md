# ADR 0001 — Modular monolith

Status: Proposed / review required
Date: 2026-11-17

Context: The product should run as a self-hosted multi-client platform on ordinary cPanel PHP/MySQL hosting without mandatory Redis, root, containers or always-on processes.

Decision: Laravel 13 modular monolith; thin HTTP controllers, narrow domain services/contracts, no generic repository wrappers or microservice split. Persistent data in MySQL 8.

Consequences: Deployment remains simple but tenant scoping, security tests and module boundaries must be actively enforced. Split services later only on measured need.
