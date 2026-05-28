import { vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({
    showError: vi.fn(),
    showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => ({
    translate: (_app: string, text: string) => text,
    t: (_app: string, text: string) => text,
}))