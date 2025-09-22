const fs = require('fs');
const path = require('path');
const glob = require('glob');

module.exports = function (grunt) {
    require('load-grunt-tasks')(grunt)

    const copyFiles = [
        'app/**',
        'assets/**',
        'core/**',
        'languages/**',
        'uninstall.php',
        'wpmudev-plugin-test.php',
        'vendor/**',
        '!**/*.map',
        'QUESTIONS.md',
        'README.md',
        'composer.json',
        'package.json',
        'Gruntfile.js',
        'gulpfile.js',
        'webpack.config.js',
        'phpcs.ruleset.xml',
        'phpunit.xml.dist',
        '!tests/**',
        '.babelrc'
    ]

    const excludeCopyFilesPro = copyFiles
        .slice(0)
        .concat([
            'changelog.txt',
        ])

    const changelog = grunt.file.read('.changelog')

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        // Clean temp folders and release copies.
        clean: {
            temp: {
                src: ['**/*.tmp', '**/.afpDeleted*', '**/.DS_Store'],
                dot: true,
                filter: 'isFile',
            },
            // Only clean assets inside build, not in dev!
            assets: ['build/<%= pkg.name %>/assets/css/**', 'build/<%= pkg.name %>/assets/js/**'],
            folder_v2: ['build/**'],
        },

        checktextdomain: {
            options: {
                text_domain: 'wpmudev-plugin-test',
                keywords: [
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d',
                ],
            },
            files: {
                src: [
                    'app/templates/**/*.php',
                    'core/**/*.php',
                    '!core/external/**', // Exclude external libs.
                    'google-analytics-async.php',
                ],
                expand: true,
            },
        },

        copy: {
            pro: {
                src: excludeCopyFilesPro,
                dest: 'build/<%= pkg.name %>/',
            },
        },

        compress: {
            pro: {
                options: {
                    mode: 'zip',
                    archive: './build/<%= pkg.name %>-<%= pkg.version %>.zip',
                },
                expand: true,
                cwd: 'build/<%= pkg.name %>/',
                src: ['**/*'],
                dest: '<%= pkg.name %>/',
            },
        },
    })

    grunt.loadNpmTasks('grunt-search')

    // Basic optimization: prune Google API services
    grunt.registerTask('prune-google-services', 'Remove unused Google API services', function () {
        const json = grunt.file.readJSON('package.json');
        const serviceDir = `build/${json.name}/vendor/google/apiclient-services/src`;
        const keepFiles = ['Drive.php', 'DriveActivity.php', 'DriveLabels.php'];
        const keepDirs = ['Drive', 'DriveActivity', 'DriveLabels'];

        if (!fs.existsSync(serviceDir)) {
            grunt.log.writeln(`Service directory not found: ${serviceDir} - skipping pruning`);
            return;
        }

        fs.readdirSync(serviceDir).forEach(file => {
            const fullPath = path.join(serviceDir, file);
            const isDir = fs.lstatSync(fullPath).isDirectory();

            if (isDir && !keepDirs.includes(file)) {
                fs.rmSync(fullPath, { recursive: true, force: true });
                grunt.log.writeln(`Removed directory: ${file}`);
            } else if (!isDir && !keepFiles.includes(file)) {
                fs.rmSync(fullPath, { force: true });
                grunt.log.writeln(`Removed file: ${file}`);
            }
        });

        grunt.log.ok('Pruned Google API services: kept only Drive, DriveActivity, DriveLabels.');
    });

    // Basic optimization: prune vendor files
    grunt.registerTask('prune-vendor', 'Remove unnecessary vendor files', function () {
        const json = grunt.file.readJSON('package.json');
        const buildDir = `build/${json.name}/vendor`;

        if (!fs.existsSync(buildDir)) {
            grunt.log.writeln(`Vendor directory not found: ${buildDir} - skipping pruning`);
            return;
        }

        const removePatterns = [
            '**/*.md',
            '**/*.txt',
            '**/*.json',
            '**/*.xml',
            '**/*.yml',
            '**/*.yaml',
            '**/*.dist',
            '**/*.lock',
            '**/*.bat',
            '**/*.sh',
            '**/*.cnf',
            '**/*.asc',
            '**/*.pubkey',
            '**/CHANGELOG*',
            '**/README*',
            '**/LICENSE*',
            '**/SECURITY*',
            '**/UPGRADING*',
            '**/AUTHORS*',
            '**/BACKERS*',
            '**/composer.json',
            '**/package.json',
            '**/package-lock.json',
            '**/phpunit.xml*',
            '**/psalm.xml',
            '**/psalm-autoload.php',
            '**/function.php',
            '**/build-phar.sh',
            '**/dist/**',
            '**/other/**',
            '**/docs/**',
            '**/tests/**',
            '**/test/**',
            '**/Test/**',
            '**/spec/**',
            '**/example/**',
            '**/examples/**',
            '**/demo/**',
            '**/demos/**',
            '**/sample/**',
            '**/samples/**',
            '**/benchmark/**',
            '**/benchmarks/**',
            '**/fixture/**',
            '**/fixtures/**',
            '**/mock/**',
            '**/mocks/**',
            '**/stub/**',
            '**/stubs/**',
            '**/mockery/**',
            '**/hamcrest/**',
            '**/phpunit/**',
            '**/sebastian/**',
            '**/phar-io/**',
            '**/theseer/**',
            '**/voku/**',
            '**/ralouphie/**',
            '**/symfony/deprecation-contracts/**',
            '**/paragonie/random_compat/**',
            '**/paragonie/constant_time_encoding/**',
            '**/phpseclib/**',
            '**/monolog/**',
            '**/guzzlehttp/promises/**',
            '**/guzzlehttp/psr7/**',
            '**/psr/cache/**',
            '**/psr/http-client/**',
            '**/psr/http-factory/**',
            '**/psr/http-message/**',
            '**/psr/log/**',
            '**/firebase/php-jwt/**',
            '**/google/auth/**'
        ];

        removePatterns.forEach(pattern => {
            const files = glob.sync(pattern, { cwd: buildDir, nodir: true });
            files.forEach(file => {
                const fullPath = path.join(buildDir, file);
                if (fs.existsSync(fullPath)) {
                    fs.unlinkSync(fullPath);
                    grunt.log.writeln(`Removed: ${file}`);
                }
            });
        });

        grunt.log.ok('Pruned vendor files: removed documentation, tests, and unnecessary files.');
    });

    grunt.registerTask('simple-build', 'Simple build without dependency scoping', function () {
        grunt.log.writeln('Building plugin without dependency scoping...');
        grunt.log.ok('Simple build completed successfully!');
    });

    grunt.registerTask('version-compare', ['search'])
    grunt.registerTask('finish', function () {
        const json = grunt.file.readJSON('package.json')
        const file = './build/' + json.name + '-' + json.version + '.zip'
        grunt.log.writeln('Process finished.')
        grunt.log.writeln('----------')
    })

    grunt.registerTask('build', [
        'checktextdomain',
        'simple-build',
        'copy:pro',
        'prune-google-services',
        //'prune-vendor',
        'compress:pro',
    ])

    grunt.registerTask('build:pro', ['build'])

    grunt.registerTask('scope-deps', ['simple-build'])

    // Pre-build clean task (SAFE – no dev asset deletion)
    grunt.registerTask('preBuildClean', [
        'clean:temp',
        'clean:folder_v2',
        'clean:assets', // now only cleans assets in build/
    ])
}

