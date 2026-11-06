<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Default seeded accounts

The database seeder creates two admin accounts, one student account, and one professor account using the listed email addresses. Set unique passwords for all four accounts in the deployment environment or an untracked local `.env` file. Do not commit these values or use shared/default passwords.

Configure these variables:

```dotenv
DEFAULT_ADMIN_1_PASSWORD="a-unique-password-of-at-least-12-characters"
DEFAULT_ADMIN_2_PASSWORD="another-unique-password-of-at-least-12-characters"
DEFAULT_STUDENT_PASSWORD="a-unique-student-password-of-at-least-12-characters"
DEFAULT_PROFESSOR_PASSWORD="a-unique-professor-password-of-at-least-12-characters"
```

On Render, add these four variables in the web service's **Environment** settings before deploying. Use unique passwords of at least 12 characters; do not use `password123`.

The seeded accounts are `admin1@codelogic.com` (Admin One), `admin2@codelogic.com` (Admin Two), `student@codelogic.com` (Student User), and `professor@codelogic.com` (Professor User). Passwords are hashed before storage. Seeding is repeatable and does not reset passwords for accounts that already exist. Student accounts created by the seeder are whitelisted.

The Docker container's `entrypoint.sh` runs migrations and seeders on startup. For a manual deployment:

```bash
php artisan migrate
php artisan db:seed
```

After the initial setup, an admin can create professor and student accounts in the admin area. Professors can view the user directory and filter accounts by role or creator, but cannot create accounts there. Students who register themselves remain subject to the existing professor-whitelist process.

Existing accounts keep working. Their creator appears as “System / public registration” unless there is a known creator association; future registrations from professor-whitelisted email addresses are attributed to the professor who added them.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
