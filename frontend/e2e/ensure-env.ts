import { execSync } from 'node:child_process'
import { copyFileSync, existsSync, readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))

export const BACKEND_DIR = resolve(__dirname, '..', '..', 'backend')

/**
 * Garantit l'existence de backend/.env.e2e AVANT le demarrage des webServers
 * et du global-setup — d'ou l'appel depuis playwright.config.ts (le chargement
 * de la config precede tout le reste).
 *
 * Le fichier n'est plus versionne (une APP_KEY fonctionnelle n'a rien a faire
 * dans git) : il est cree depuis .env.e2e.example et la cle est generee
 * localement au premier run. Garde-fou critique : sans .env.e2e, `--env=e2e`
 * retomberait silencieusement sur le .env de dev et `migrate:fresh`
 * detruirait la base « ledge ».
 */
export function ensureE2eEnvFile(): void {
  const envPath = join(BACKEND_DIR, '.env.e2e')

  if (!existsSync(envPath)) {
    copyFileSync(join(BACKEND_DIR, '.env.e2e.example'), envPath)
  }

  const appKeyManquante = /^APP_KEY=\s*$/m.test(readFileSync(envPath, 'utf-8'))
  if (appKeyManquante) {
    // .env.e2e existe a ce stade : key:generate ecrit bien dedans (--env=e2e).
    execSync('php artisan key:generate --env=e2e --force', {
      cwd: BACKEND_DIR,
      stdio: 'inherit',
    })
  }
}
