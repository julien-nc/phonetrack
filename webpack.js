const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
    colors: true,
    modules: false,
}

webpackConfig.entry = {
    admin: { import: path.join(__dirname, 'src', 'admin.js'), filename: 'admin.js' },
    phonetrack: { import: path.join(__dirname, 'src', 'phonetrack.js'), filename: 'phonetrack.js' },
}

module.exports = webpackConfig
