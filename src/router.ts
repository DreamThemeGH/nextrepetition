/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — Vue Router
 */

import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/views/Dashboard.vue'),
    },
    {
        path: '/decks',
        name: 'decks',
        component: () => import('@/views/DeckBrowser.vue'),
    },
    {
        path: '/study/:path(.*)',
        name: 'study',
        component: () => import('@/views/StudySession.vue'),
        props: true,
    },
    {
        path: '/cards/:path(.*)',
        name: 'cards',
        component: () => import('@/views/CardBrowser.vue'),
        props: true,
    },
    {
        path: '/statistics',
        name: 'statistics',
        component: () => import('@/views/Statistics.vue'),
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/Settings.vue'),
    },
]

export const router = createRouter({
    history: createWebHashHistory(),
    routes,
})
