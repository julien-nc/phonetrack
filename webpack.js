const webpack = require('webpack')
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

webpackConfig.plugins.push(
    /* Use the ProvidePlugin constructor to inject jquery implicit globals */
    new webpack.ProvidePlugin({
        $: "jquery",
        jQuery: "jquery",
        "window.jQuery": "jquery'",
        "window.$": "jquery"
    })
)

module.exports = webpackConfig
