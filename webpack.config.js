const Encore = require('@symfony/webpack-encore');
const path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    .addEntry('app', './assets/app.tsx')

    .splitEntryChunks()
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .enableTypeScriptLoader()
    .enableReactPreset()
    .enablePostCssLoader()

    .copyFiles({
        from: './node_modules/cm-chessboard/assets',
        to: 'cm-chessboard/[path][name].[ext]',
    })

    .copyFiles({
        from: './node_modules/stockfish/bin',
        pattern: /stockfish-18-lite-single\.js$/,
        to: '[name].[ext]',
    })

    .copyFiles({
        from: './node_modules/stockfish/bin',
        pattern: /stockfish-18-lite-single\.wasm$/,
        to: 'stockfish.[ext]',
    })

    .addAliases({
        '@': path.resolve(__dirname, 'assets'),
    })
;

module.exports = Encore.getWebpackConfig();
