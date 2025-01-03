# FamilyTree Application

A Laravel-based family tree management application.

## Requirements

- PHP >= 8.2
- MySQL/MariaDB
- Composer
- Node.js & NPM

## Quick Setup

1. **Clone and Install**

git clone https:/f/github.com/Kukaps/FamilyTree.git
cd FamilyTree
composer install
npm install

2. **Configuration**

cp .env.example .env
php artisan key:generate

Update `.env` with your MySQL details

php artisan migrate

npm run dev
php artisan serve
