const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    mode: isProduction ? 'production' : 'development',
    entry: {
        'drivetestpage': './src/googledrive-page/main.jsx'
    },
    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: 'js/[name].min.js',
        clean: false,
    },
    module: {
        rules: [
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env', '@babel/preset-react'],
                        plugins: [
                            '@babel/plugin-transform-class-properties',
                            '@babel/plugin-transform-runtime'
                        ]
                    }
                }
            },
            {
                test: /\.scss$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'sass-loader'
                ]
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].min.css',
        }),
    ],
    externals: {
        'react': 'React',
        'react-dom': 'ReactDOM',
        '@wordpress/components': 'wp.components',
        '@wordpress/element': 'wp.element',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/api-fetch': 'wp.apiFetch',
        '@wpmudev/shared-ui': 'SUI'
    },
    resolve: {
        extensions: ['.js', '.jsx', '.scss']
    }
};
