// node save.js <plugin-relative-file> ...  (uploads src/<file with / -> __>)
const { admin } = require('./lib'); const fs = require('fs');
const files = process.argv.slice(2).map(f => ({ f, c: fs.readFileSync('src/' + f.replace(/\//g, '__'), 'utf8') }));
admin(async (files) => {
  const out = [];
  for (const { f, c } of files) {
    const url = '/wp-admin/plugin-editor.php?plugin=aluglobus-staging-catalog%2Faluglobus-staging-catalog.php&file=' + encodeURIComponent(f);
    const d = new DOMParser().parseFromString(await (await fetch(url)).text(), 'text/html');
    const fd = new FormData();
    fd.append('nonce', d.querySelector('#template input[name=nonce]').value);
    fd.append('_wp_http_referer', url); fd.append('newcontent', c); fd.append('action', 'edit-theme-plugin-file');
    fd.append('file', f); fd.append('plugin', 'aluglobus-staging-catalog/aluglobus-staging-catalog.php');
    const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd });
    const t = await r.text(); out.push(f + ' ' + r.status + ' ' + t.slice(0, 300));
    // verify read-back
    const d2 = new DOMParser().parseFromString(await (await fetch(url)).text(), 'text/html');
    out.push('readback ' + (d2.querySelector('#newcontent').value === c ? 'MATCH' : 'DIFF len ' + d2.querySelector('#newcontent').value.length + ' vs ' + c.length));
  }
  return out.join('\n');
}, files).then(console.log).catch(e => console.log('FAIL', e.message));
