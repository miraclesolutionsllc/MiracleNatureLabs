const { defineConfig } = require('cypress')

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost:8000', // Adjust if using a different port
    supportFile: false,
    specPattern: 'tests/**/*.cy.js'
  }
})