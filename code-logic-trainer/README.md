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

The seeded accounts are `admin1@codelogic.com` (Admin One), `admin2@codelogic.com` (Admin Two), `student@codelogic.com` (Student User), and `professor@codelogic.com` (Professor User). These are login names, not mailboxes. Passwords are hashed before storage. Seeding is repeatable and does not reset passwords for accounts that already exist. Student accounts created by the seeder are approved and whitelisted. Seeded users should add a private recovery email in their profile before relying on password recovery.

Public registration asks for a personal email address, year level, student ID, and password, then creates a pending student account with a generated `@codelogic.com` login address. Year level and student ID are required for new registrations; their database columns remain nullable so legacy accounts continue to work until updated. The personal email is private and is used for account and password-recovery notifications. New login addresses are shown after registration and in the confirmation email. Professors and admins can review pending accounts in the user directory; professors may approve student accounts only after assigning the student to one of their classes, while only admins can change roles or suspend accounts. The professor's managed-student area uses the existing classrooms and classroom-student pivot for class assignment. Approval also authorizes the student for the existing whitelist gate. Removing a student from a professor's whitelist continues to revoke student access.

Students can view and update their own name, private recovery email, and optional age in their profile; their Code Logic login address, student ID, year level, and enrolled classes are shown there. Professors can manage name, recovery email, age, year level, student ID, and class for students connected to them through account attribution, an active whitelist entry, or one of their classrooms. Student quiz history combines completed self-study attempts with completed live-session participation records and uses saved question explanations where available.

Existing users keep their current email value as their login address, and the migration copies existing non-`@codelogic.com` addresses into the private recovery field. Existing `@codelogic.com` logins are not treated as mailboxes; those users need to set a recovery email in their profile.

Before deploying the schema change, back up the production database. Then run the migration once against the intended database:

```bash
php artisan migrate --force
```

Do not use `migrate:fresh` on an existing deployment. The existing Docker `entrypoint.sh` also runs migrations at startup; choose either that deployment path or a controlled manual migration, and do not point either at production until you have reviewed the migration and backup.

The application queues registration and approval notifications. Configure a real Laravel mail transport and sender address, then run a persistent queue worker (for example, `php artisan queue:work --tries=3`) in the deployment environment. The default mailer in `config/mail.php` is `log`, so real delivery is not enabled unless the deployment environment configures a mail transport. Password reset links expire after 60 minutes. Failed queue jobs should be monitored and retried after mail configuration issues are fixed.

After the migration and deployment, verify login and role-based pages, set private recovery email addresses for existing accounts that do not have one, configure/verify mail delivery, and ensure the queue worker is running. The migration keeps existing accounts approved to avoid locking them out. The student-profile migration adds nullable age/year-level/student-ID columns; existing students are not backfilled or blocked, and professors can complete legacy details. Existing creator attribution remains unchanged; new registrations using a professor-whitelisted personal email are attributed to that professor.

## Learning Laravel

### Student AI quiz generation on Render

Professors and students can also generate document-based quizzes through Gemini. Set `GEMINI_API_KEY` and, if needed, `GEMINI_MODEL` in the server environment. A blank or zero question count requests automatic generation, up to 40 validated questions; requested counts must be between 1 and 40. Multiple-choice and fill-in-the-blank formats are supported. Fill-in-the-blank grading ignores case and repeated/leading/trailing whitespace but does not ignore punctuation. Malformed and duplicate AI questions are discarded; the quiz shows the actual saved question count. Student and professor AI generation is limited to three requests per user per minute.

Uploaded PDFs are stored on Laravel's configured `local` disk (currently rooted at `storage/app/private`) and are not linked through the public storage symlink. Extracted document text is sent to the configured Gemini service for quiz generation. Review the provider's current data-handling terms before uploading sensitive material; this application does not configure or guarantee provider-side retention or training behavior. The application currently has no document-retention cleanup job, and deleting a quiz does not delete its source PDF. The professor quiz page marks questions for which the AI recommends review, but there is no question-editing or publishing-approval workflow; professors should verify flagged questions before starting a live session.

The quiz-format migration adds nullable source-document ownership for student uploads and defaulted quiz/question format columns, so existing quiz and question rows remain multiple-choice and usable. Back up the target database, then apply migrations in the intended environment:

```bash
php artisan migrate
```

For a controlled production deployment, use `php artisan migrate --force` only after review and backup. Do not use `migrate:fresh` on an existing database. Existing PDFs and quiz data are not backfilled or deleted; the new student document association is used only for newly generated document quizzes.
Rolling this migration back is blocked while student-owned PDF records exist, so preserve or explicitly handle those records before attempting a rollback.

Student-generated quizzes use Groq. In the Render web service's **Environment** settings, set `GROQ_API_KEY` and set `GROQ_MODEL` to an active model enabled for that key. The application default is `openai/gpt-oss-120b`. An explicit Render `GROQ_MODEL` value overrides the application default, so replace any stale value such as `llama-3.1-8b-instant`, then redeploy. After changing environment values, the container startup clears and rebuilds Laravel's configuration cache.

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
