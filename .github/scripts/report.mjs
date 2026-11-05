// Builds the GitHub Pages site from the CI reports: shields.io endpoint badges in badges/ and an
// index page linking every report.
//   node .github/scripts/report.mjs <site dir>
// Env: RESULTS (toJSON(needs)), RUN_URL, COMMIT, PLUGIN (frankenstyle), REPO (owner/name).
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const site = process.argv[2] ?? 'site';
const results = JSON.parse(process.env.RESULTS ?? '{}');
const plugin = process.env.PLUGIN ?? 'plugin';
const read = (path) => (existsSync(join(site, path)) ? readFileSync(join(site, path), 'utf8') : null);

/** Line coverage from a Clover XML report, as a percentage. */
function cloverPercent(xml) {
  const m = xml?.match(/<metrics files="\d+"[^>]*\bstatements="(\d+)"[^>]*\bcoveredstatements="(\d+)"/);
  if (!m) return null;
  const [total, covered] = [Number(m[1]), Number(m[2])];
  return total === 0 ? 100 : (covered / total) * 100;
}

const colour = (p) => (p === null ? 'lightgrey' : p >= 95 ? 'brightgreen' : p >= 85 ? 'green' : p >= 70 ? 'yellow' : 'red');
const pct = (v) => (v === null ? 'unknown' : `${Math.floor(v * 10) / 10}%`);
const status = results.moodle?.result ?? 'unknown';
const coverage = cloverPercent(read('php-coverage/clover.xml'));

mkdirSync(join(site, 'badges'), { recursive: true });
const badge = (name, label, message, color) =>
  writeFileSync(join(site, 'badges', `${name}.json`), JSON.stringify({ schemaVersion: 1, label, message, color }));
badge('php-coverage', 'PHP coverage', pct(coverage), colour(coverage));
badge('moodle', 'PHPUnit + Behat', status === 'success' ? 'passing' : status, status === 'success' ? 'brightgreen' : 'red');

writeFileSync(
  join(site, 'index.html'),
  `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${plugin} test reports</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem;color:#14161a}
table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #dde;padding:.4rem;text-align:left}a{color:#2438ff}</style></head>
<body><h1>${plugin} test reports</h1>
<p>Built from commit <code>${(process.env.COMMIT ?? '').slice(0, 7)}</code>. <a href="${process.env.RUN_URL ?? '#'}">CI run</a>.</p>
<table><thead><tr><th>Check</th><th>Result</th><th></th></tr></thead><tbody>
<tr><td>PHP coverage</td><td>${pct(coverage)}</td><td><a href="php-coverage/">report</a></td></tr>
<tr><td>Moodle PHPUnit and Behat (all supported versions)</td><td>${status}</td><td><a href="${process.env.RUN_URL ?? '#'}">run</a></td></tr>
</tbody></table>
<p><a href="https://github.com/${process.env.REPO ?? ''}">Source on GitHub</a> · Built by <a href="https://devlms.com">DevLMS</a></p></body></html>
`,
);
console.log({ coverage: pct(coverage), moodle: status });
