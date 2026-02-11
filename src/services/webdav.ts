/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Flashcards v2 — WebDAV folder listing service
 *
 * Fetches folder tree from Nextcloud Files via WebDAV PROPFIND.
 */

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

    responses.forEach((resp) => {
        const href = resp.querySelector('href')?.textContent || ''
        const isCollection = resp.querySelector('resourcetype collection') !== null

        if (!isCollection) return

        // Extract path relative to user's files root
        const decodedHref = decodeURIComponent(href)
        const userFilesPrefix = `/remote.php/dav/files/${getCurrentUser()?.uid}/`
        let relativePath = decodedHref
        const idx = decodedHref.indexOf(userFilesPrefix)
        if (idx >= 0) {
            relativePath = '/' + decodedHref.substring(idx + userFilesPrefix.length)
        }

        // Remove trailing slash
        relativePath = relativePath.replace(/\/+$/, '') || '/'

        // Skip the queried folder itself
        if (relativePath === basePath || relativePath === basePath.replace(/\/+$/, '')) {
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
    if (!user?.uid) throw new Error('Not authenticated')

    const davUrl = generateRemoteUrl(`dav/files/${user.uid}${path}`)

    const response = await fetch(davUrl, {
        method: 'PROPFIND',
        headers: {
            'Depth': '1',
            'Content-Type': 'application/xml',
        },
        body: `<?xml version="1.0" encoding="utf-8" ?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:resourcetype />
    <d:displayname />
  </d:prop>
</d:propfind>`,
    })

    if (!response.ok) {
        throw new Error(`WebDAV PROPFIND failed: ${response.status}`)
    }

    const xml = await response.text()
    return parseWebDavResponse(xml, path)
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
