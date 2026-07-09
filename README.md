# Associação de Santana

Aplicação Laravel + Inertia/Vue para gestão de mesas, pedidos, reservas, sócios, cotas e utilizadores.

## Requisitos

- PHP 8.2+
- MySQL 8+
- Node.js 18+
- Composer

## Instalação

1. `git clone` ou copiar projeto
2. `cp .env.example .env`
3. Configurar `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` no `.env`
4. `composer install`
5. `php artisan key:generate`
6. `php artisan migrate:fresh --seed`
7. `npm install && npm run build`
8. `php artisan serve`

## Utilizadores de Teste

| Email | Password | Role |
|---|---|---|
| admin@santana.pt | password | Administrador |
| gerente@santana.pt | password | Gerente |
| bar@santana.pt | password | Staff Bar |
| cozinha@santana.pt | password | Staff Cozinha |
| tesoureiro@santana.pt | password | Tesoureiro |

## Ecrãs de Secção

Sem login:

- `/secao/bebidas`
- `/secao/sobremesas`
- `/secao/acompanhamentos`
