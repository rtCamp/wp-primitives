/**
 * ESLint flat config.
 *
 * Extends `@rtcamp/eslint-config`.
 * @see https://github.com/rtCamp/wp-tooling/tree/main/node-packages/eslint-config
 */
import rtCampConfig from '@rtcamp/eslint-config';

export default [
	...rtCampConfig,
	{
		ignores: [
			'**/*.min.js',
			'**/node_modules/**',
			'**/vendor/**',
			'**/tests/_output/**',
		],
	},
];
