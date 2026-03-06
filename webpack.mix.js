// webpack.mix.js
const mix = require('laravel-mix');

const sassOptions = {
    sassOptions: {
        quietDeps: true,
        silenceDeprecations: ['color-functions', 'global-builtin', 'import'],
    }
};

mix.js('resources/js/activity.js', 'public/js')
   .js('resources/js/dashboard.js', 'public/js')
   .sass('resources/css/app.scss', 'public/css', sassOptions)
   .sass('resources/css/signin.scss', 'public/css', sassOptions);
