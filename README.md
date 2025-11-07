# TT Oro Project

A Symfony-based project.

---

## 🧰 Setup Instructions

Clone the repository and install dependencies:

```bash
git clone git@github.com:malesh-v/tt-oro.git
cd tt-oro
echo 'DEFAULT_URI=http://localhost' > .env
composer install
```

---

## 🚀 Usage

### Run the main command
```bash
bin/console foo:hello
```

### Run a member of the command chain individually
```bash
bin/console bar:hi
```

---

## 🧪 Running Tests

Run unit tests for the `ChainCommandBundle`:

```bash
vendor/bin/phpunit src/ChainCommandBundle/Tests
```

---

## 📜 Logs

To view the development log file:

```bash
cat var/log/dev.log
```

---

## 📝 Notes

- Make sure PHP, Composer, and Symfony CLI (optional) are installed.  
- The `.env` file defines the required environment variable `DEFAULT_URI`.  
- Run commands from the project root.
