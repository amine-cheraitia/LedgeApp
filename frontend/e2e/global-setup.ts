import { execSync } from 'node:child_process'
import { writeFileSync, unlinkSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))

/**
 * Global setup Playwright — prepare l'environnement E2E AVANT tout test :
 *   1. Cree la base MySQL `ledge_e2e` si elle n'existe pas encore.
 *   2. Rejoue les migrations + seeders standards (APP_ENV=e2e -> backend/.env.e2e).
 *   3. Seede les comptes de test dedies E2E (secretaire.e2e@, collaborateur.e2e@).
 *
 * Ne touche JAMAIS la base de dev « ledge » : tout est scope a `ledge_e2e`
 * via backend/.env.e2e (DB_DATABASE=ledge_e2e).
 */
const BACKEND_DIR = resolve(__dirname, '..', '..', 'backend')

function createDatabaseIfMissing(): void {
  const phpScript = [
    '<?php',
    'try {',
    "    \$pdo = new PDO('mysql:host=127.0.0.1', 'root', '');",
    "    \$pdo->exec('CREATE DATABASE IF NOT EXISTS ledge_e2e CHARACTER SET utf8mb4');",
    '    echo "ledge_e2e OK\\n";',
    '} catch (Throwable $e) {',
    '    fwrite(STDERR, $e->getMessage() . "\\n");',
    '    exit(1);',
    '}',
    '',
  ].join('\n')

  const tempFile = join(tmpdir(), `ledge-e2e-create-db-${Date.now()}.php`)
  writeFileSync(tempFile, phpScript, 'utf-8')

  try {
    execSync(`php "${tempFile}"`, { stdio: 'inherit' })
  } finally {
    unlinkSync(tempFile)
  }
}

export default async function globalSetup(): Promise<void> {
  createDatabaseIfMissing()

  execSync('php artisan migrate:fresh --seed --env=e2e --force', {
    cwd: BACKEND_DIR,
    stdio: 'inherit',
  })

  execSync('php artisan db:seed --class=E2eSeeder --env=e2e --force', {
    cwd: BACKEND_DIR,
    stdio: 'inherit',
  })
}
