import { execFileSync } from 'node:child_process';

const changes = execFileSync('git', ['status', '--porcelain=v1', '--untracked-files=all', '--', 'public/build', 'public/frontend'], {
    encoding: 'utf8',
}).trim();

if (changes) {
    console.error('Build output differs from the committed assets. Run npm run build and commit public/build and public/frontend.');
    console.error(changes);
    process.exitCode = 1;
} else {
    console.log('Committed assets match the source build.');
}
