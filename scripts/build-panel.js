// Reuse the bundled JSMin; no package install is needed.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const context = vm.createContext({});
vm.runInContext(fs.readFileSync(path.join(root, 'src/js/jsmin.js'), 'utf8'), context);
for (const name of ['loadxsspanel', 'loadjstemplate', 'loadmyjs']) {
    const source = fs.readFileSync(path.join(root, 'src/js/' + name + '.js'), 'utf8');
    const output = context.jsmin(source, 2);
    new vm.Script(output);
    fs.writeFileSync(path.join(root, 'static/js/' + name + '.min.js'), output + '\n');
    console.log('Built ' + name + ' with bundled JSMin (level 2)');
}
