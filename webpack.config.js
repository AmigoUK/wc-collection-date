const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    entry: {
      'admin': './assets/js/admin.js',
      'checkout': './assets/js/checkout.js',
      'admin-styles': './assets/css/admin.css',
      'checkout-styles': './assets/css/checkout.css'
    },
    output: {
      path: path.resolve(__dirname, 'assets/dist'),
      filename: 'js/[name].min.js',
      clean: true
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env']
            }
          }
        },
        {
          test: /\.css$/,
          use: [
            MiniCssExtractPlugin.loader,
            'css-loader'
          ]
        }
      ]
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: 'css/[name].min.css'
      })
    ],
    optimization: {
      minimize: isProduction,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            compress: {
              drop_console: isProduction, // Remove console.log in production
              drop_debugger: true,
              pure_funcs: isProduction ? ['console.log', 'console.info', 'console.debug'] : []
            },
            format: {
              comments: false
            }
          },
          extractComments: false
        }),
        new CssMinimizerPlugin()
      ]
    },
    devtool: isProduction ? false : 'source-map'
  };
};
