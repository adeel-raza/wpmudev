module.exports = {
	extends: [
		'eslint:recommended',
		'plugin:react/recommended',
		'plugin:react-hooks/recommended',
	],
	plugins: [
		'@wordpress/eslint-plugin',
		'react',
		'react-hooks',
	],
	env: {
		browser: true,
		es6: true,
		node: true,
	},
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module',
		ecmaFeatures: {
			jsx: true,
		},
	},
	settings: {
		react: {
			version: 'detect',
		},
	},
	rules: {
		'@wordpress/no-global-event-listener': 'off',
		'@wordpress/no-unsafe-wp-apis': 'off',
		'react/jsx-uses-react': 'off',
		'react/react-in-jsx-scope': 'off',
		'react/prop-types': 'off',
		'no-unused-vars': 'warn',
		'no-console': 'warn',
		'prefer-const': 'error',
		'no-var': 'error',
	},
};
