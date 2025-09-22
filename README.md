# WPMUDEV Test Plugin #

This is a plugin that can be used for testing coding skills for WordPress and PHP.

# Development

## Composer
Install composer packages
`composer install`

## Build Tasks (npm)
Everything should be handled by npm.

Install npm packages
`npm install`

| Command              | Action                                                |
|----------------------|-------------------------------------------------------|
| `npm run watch`      | Compiles and watch for changes.                       |
| `npm run compile`    | Compile production ready assets.                      |
| `npm run build`  | Build production ready bundle inside `/build/` folder |

## Package Optimization

The build process includes automatic pruning of unused Google services files to reduce the final zip size:

- **Grunt Task**: `prune-google-services` removes unused Google API files from the vendor directory
- **Size Reduction**: Significantly reduces build size by removing unnecessary Google service dependencies
- **Functionality**: Maintains all required Google Drive API functionality while optimizing package size
