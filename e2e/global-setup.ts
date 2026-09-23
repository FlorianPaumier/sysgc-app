import { execFileSync } from 'node:child_process';

export default function globalSetup(): void {
    if (process.env.E2E_SEED === '0') {
        return;
    }

    const command = process.env.E2E_SEED_COMMAND;
    if (command) {
        execFileSync('sh', ['-c', command], { stdio: 'inherit' });
        return;
    }

    execFileSync('docker', [
        'compose', 'exec', '-T', 'php',
        'php', 'bin/console', 'app:e2e:seed', '--env=dev',
    ], { stdio: 'inherit' });
}
