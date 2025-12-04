# WPMUDEV Test Plugin #

This is a plugin that can be used for testing coding skills for WordPress and PHP.

# Development

---<h2 align="center">💝 Support This Project</h2><p align="center"><strong>If you find this project helpful, please consider supporting it:</strong></p><p align="center"><a href="https://link.elearningevolve.com/self-pay" target="_blank"><img src="https://img.shields.io/badge/Support%20via%20Stripe-635BFF?style=for-the-badge&logo=stripe&logoColor=white" alt="Support via Stripe" height="50" width="300"></a></p><p align="center"><a href="https://link.elearningevolve.com/self-pay" target="_blank"><strong>👉 Click here to support via Stripe 👈</strong></a></p>---## Composer
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
