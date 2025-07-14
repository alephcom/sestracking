// webpack.mix.js
const mix = require('laravel-mix');

mix.js('resources/js/activity.js', 'public/js')
   .js('resources/js/dashboard.js', 'public/js')
   .sass('resources/css/app.scss', 'public/css')
   .sass('resources/css/signin.scss', 'public/css')
   .vue();
