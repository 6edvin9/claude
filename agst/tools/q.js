// usage: node q.js file.js  -> runs exported async fn(text) in wp-admin, prints JSON
const { admin } = require('./lib');
const code = require('fs').readFileSync(process.argv[2], 'utf8');
admin(async (code) => { const nonce = (await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim(); window.N = nonce;
  const H = { 'X-WP-Nonce': nonce }; const f = eval('(' + code + ')'); try { return JSON.stringify(await f(H, nonce)); } catch (e) { return 'ERR ' + e.stack; } }, code)
 .then(r => console.log(r)).catch(e => console.log('FAIL', e.message));
