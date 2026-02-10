/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Vite config
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import path from 'path'
import { fileURLToPath } from 'url'
import fs from 'fs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

const appName = 'flashcards'

export default defineConfig(({ mode }) => {
    const isProduction = mode === 'production'

    return {
        plugins: [
            vue(),
            // Copy CSS from js/css/ to css/ after build so Util::addStyle can find it
            {
                name: 'copy-css-to-root',
                closeBundle() {
                    const srcDir = path.resolve(__dirname, 'js', 'css')
                    const destDir = path.resolve(__dirname, 'css')
                    if (fs.existsSync(srcDir)) {
                        if (!fs.existsSync(destDir)) {
                            fs.mkdirSync(destDir, { recursive: true })
                        }
                        for (const file of fs.readdirSync(srcDir)) {
                            if (file.endsWith('.css')) {
                                fs.copyFileSync(
                                    path.join(srcDir, file),
                                    path.join(destDir, file),
                                )
                            }
                        }
                    }
                },
            },
        ],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'src'),
            },
        },
        define: {
            'process.env.NODE_ENV': JSON.stringify(mode),
        },
        build: {
            outDir: path.resolve(__dirname, 'js'),
            emptyOutDir: true,
            minify: isProduction,
            sourcemap: !isProduction,
            lib: {
                entry: path.resolve(__dirname, 'src/main.ts'),
                name: appName,
                fileName: () => `${appName}-main.js`,
                formats: ['iife'],
            },
            rollupOptions: {
                output: {
                    assetFileNames: (chunkInfo) => {
                        if (chunkInfo.names?.[0]?.endsWith('.css')) {
                            return 'css/[name].css'
                        }
                        return 'assets/[name]-[hash][extname]'
                    },
                },
            },
        },
        css: {
            preprocessorOptions: {
                scss: {
                    additionalData: `@use "@/assets/styles/variables" as *;`,
                },
            },
        },
        test: {
            globals: true,
            environment: 'happy-dom',
            include: ['src/**/*.{test,spec}.{ts,tsx}'],
            css: false,
            server: {
                deps: {
                    inline: [/@nextcloud/],
                },
            },
            coverage: {
                reporter: ['text', 'html'],
                include: ['src/**/*.{ts,vue}'],
                exclude: ['src/**/*.d.ts', 'src/**/*.{test,spec}.ts'],
            },
        },
    }
})
