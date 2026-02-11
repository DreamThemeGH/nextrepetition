/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — WebDAV folder listing service
 *
 * Fetches folder tree from Nextcloud Files via WebDAV PROPFIND.
 */

import axios from '@nextcloud/axios'
import { generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

export interface FolderNode {
    name: string
    path: string
    children: FolderNode[]
    loaded: boolean
}

/**
 * Parse WebDAV PROPFIND XML response into folder list.
 */
function parseWebDavResponse(xml: string, basePath: string): FolderNode[] {
    const parser = new DOMParser()
    const doc = parser.parseFromString(xml, 'application/xml')
    const responses = doc.querySelectorAll('response')
    const folders: FolderNode[] = []
    const uid = getCurrentUser()?.uid || ''

    responses.forEach((resp) => {
        const href = resp.querySelector('href')?.textContent || ''

        // Check if this is a collection (folder)
        const resourcetype = resp.querySelector('resourcetype')
        if (!resourcetype) return
        // querySelectorAll doesn't work well with namespaced elements, check innerHTML
        const isCollection = resourcetype.innerHTML.toLowerCase().includes('collection')
            || resourcetype.querySelector('collection') !== null
            || resp.querySelector('resourcetype > *') !== null

        if (!isCollection) return

        // Extract path relative to user's files root
        const decodedHref = decodeURIComponent(href)

        // Try multiple patterns to find the user files prefix
        const prefixes = [
            `/remote.php/dav/files/${uid}/`,
            `/remote.php/webdav/`,
        ]

        let relativePath = ''
        for (const prefix of prefixes) {
            const idx = decodedHref.indexOf(prefix)
            if (idx >= 0) {
                relativePath = '/' + decodedHref.substring(idx + prefix.length)
                break
            }
        }

        if (!relativePath) return

        // Remove trailing slash
        relativePath = relativePath.replace(/\/+$/, '') || '/'

        // Normalize basePath
        const normalizedBase = basePath.replace(/\/+$/, '') || '/'

        // Skip the queried folder itself
        if (relativePath === normalizedBase) {
            return
        }

        const name = relativePath.split('/').filter(Boolean).pop() || relativePath

        folders.push({
            name,
            path: relativePath,
            children: [],
            loaded: false,
        })
    })

    return folders.sort((a, b) => a.name.localeCompare(b.name))
}

/**
 * List immediate subfolders of a given path via WebDAV PROPFIND.
 */
export async function listFolders(path: string = '/'): Promise<FolderNode[]> {
    const user = getCurrentUser()
    if (!user?.uid) {
        console.warn('[flashcards] WebDAV: user not authenticated')
        return []
    }

    const davUrl = generateRemoteUrl(`dav/files/${user.uid}${path}`)

    try {
        const response = await axios({
            method: 'PROPFIND',
            url: davUrl,
            headers: {
                'Depth': '1',
                'Content-Type': 'application/xml; charset=utf-8',
            },
            data: `<?xml version="1.0" encoding="utf-8" ?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:resourcetype />
    <d:displayname />
  </d:prop>
</d:propfind>`,
        })

        const xml = typeof response.data === 'string'
            ? response.data
            : new XMLSerializer().serializeToString(response.data)

        return parseWebDavResponse(xml, path)
    } catch (e) {
        console.error('[flashcards] WebDAV PROPFIND error:', e)
        return []
    }
}

/**
 * Recursively load all subfolders up to maxDepth.
 */
export async function listFoldersRecursive(
    path: string = '/',
    maxDepth: number = 3,
): Promise<FolderNode[]> {
    if (maxDepth <= 0) return []

    const folders = await listFolders(path)

    for (const folder of folders) {
        try {
            folder.children = await listFoldersRecursive(folder.path, maxDepth - 1)
            folder.loaded = true
        } catch {
            folder.children = []
            folder.loaded = false
        }
    }

    return folders
}
