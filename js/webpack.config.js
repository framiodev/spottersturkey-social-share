const config = require('flarum-webpack-config');
const path = require('path');

// Standart konfigürasyonu al
const webConfig = config();

// Giriş noktalarını (Entry Points) ELLE zorla tanımla
webConfig.entry = {
    forum: path.resolve(__dirname, 'src/forum/index.js'),
    admin: path.resolve(__dirname, 'src/admin/index.js')
};

module.exports = webConfig;