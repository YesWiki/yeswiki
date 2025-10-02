import { execSync } from 'child_process'

export const resetEnv = () => {
  execSync('tests/reset.sh')
}
