import { execFileSync } from 'child_process'
import { fileURLToPath } from 'url'

/**
 * The script lives next to this file, wherever the checkout is.
 *
 * It used to be the absolute `/var/www/html/tests/e2e/reset.sh`, which is where the dev compose
 * file mounts the repository -- so the suite only ran inside that one container. Everywhere else,
 * CI included, the shell reported the path as "not found" and every spec that resets first failed
 * before its first assertion.
 */
const RESET = fileURLToPath(new URL('../reset.sh', import.meta.url))

export const resetEnv = () => {
  execFileSync('bash', [RESET], { stdio: 'inherit' })
}
