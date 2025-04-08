import {execSync} from 'child_process';

export const resetEnv = () => {
    execSync('/var/www/html/tests/e2e/reset.sh');
}