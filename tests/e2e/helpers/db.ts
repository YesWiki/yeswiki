import { execSync } from 'child_process'

export const resetEnv = () => {
  execSync('/var/www/html/tests/reset.sh')
}
