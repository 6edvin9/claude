const { admin } = require('./lib'); const fs = require('fs');
const files = process.argv.slice(2);
admin(async (files) => {
  const out = {};
  for (const f of files) {
    const h = await (await fetch('/wp-admin/plugin-editor.php?plugin=aluglobus-staging-catalog%2Faluglobus-staging-catalog.php&file=' + encodeURIComponent(f))).text();
    const d = new DOMParser().parseFromString(h, 'text/html');
    const ta = d.querySelector('#newcontent');
    out[f] = ta ? ta.value : 'ERR ' + h.slice(0, 300);
    if (!out.__list) out.__list = [...d.querySelectorAll('#templateside a')].map(a => a.textContent.trim()).join('\n');
  }
  return out;
}, files).then(o => { for (const [k, v] of Object.entries(o)) { fs.writeFileSync('src/' + k.replace(/\//g, '__'), v); console.log(k, v.length); } });
