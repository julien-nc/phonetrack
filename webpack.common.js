const path = require('path');
const webpack = require('webpack');
//const { VueLoaderPlugin } = require('vue-loader');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = {
	entry: {
		admin: path.join(__dirname, 'src', 'admin.js'),
		phonetrack: path.join(__dirname, 'src', 'phonetrack.js'),
	},
	output: {
		path: path.join(__dirname, 'js'),
		publicPath: "/js/",
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader'],
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader'],
			},
			//{
			//	test: /\.vue$/,
			//	loader: 'vue-loader',
			//},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/,
			},
			{
				test: /\.(png|jpg|gif|svg|woff|woff2|eot|ttf)$/,
				loader: 'url-loader',
			},
		],
	},
	plugins: [
		//new VueLoaderPlugin(),
		new CleanWebpackPlugin(),
	],
	resolve: {
		//extensions: ['*', '.js', '.vue'],
		extensions: ['*', '.js'],
	},
}
