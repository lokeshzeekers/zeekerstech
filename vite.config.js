import { defineConfig } from 'vite'
import { resolve } from 'path'

export default defineConfig({
  build: {
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html'),
        about: resolve(__dirname, 'about.html'),
        products: resolve(__dirname, 'products.html'),
        blog: resolve(__dirname, 'blog.html'),
        career: resolve(__dirname, 'career.html'),
        contact: resolve(__dirname, 'contact.html'),
        lab: resolve(__dirname, 'lab.html'),
        admin: resolve(__dirname, 'admin.html'),
        helpdesk_login: resolve(__dirname, 'helpdesk-login.html'),
        helpdesk_dashboard: resolve(__dirname, 'helpdesk-dashboard.html')
      }
    }
  }
})
