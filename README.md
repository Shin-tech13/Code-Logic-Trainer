# Code-Logic-Trainer

# Setup Instructions for Teammates
Follow these steps to run the project on your local machine:

# Prerequisites
- PHP & Composer installed
- Node.js & NPM installed
- XAMPP / MySQL running

### Step-by-Step Setup
1. **Clone the repository:**
   ```bash
   git clone [https://github.com/Shin-tech13/Code-Logic-Trainer.git](https://github.com/Shin-tech13/Code-Logic-Trainer.git)
   cd Code-Logic-Trainer

### Dependencies To Install
# Bash on Terminal / Poweshell
composer install
npm install

### env File Setup
# Bash on Terminal / Poweshell
cp .env.example .env
php artisan key:generate

### find env annd set it up
# un-comment the database and do this
DB_DATABASE=code_logic_db
DB_USERNAME=root
DB_PASSWORD=

### Run 2 Terminal / Powershell
# Terminal 1:
php artisan serve

#Terminal 2
npm run dev

Credentials for test try:

              User::firstOrCreate(
            ['email' => 'student@codelogic.com'],
            [
                'name' => 'Test Student',
                'password' => Hash::make('password123'),
            ]



##### bash before run:
to create users
php artisan migrate --seed
