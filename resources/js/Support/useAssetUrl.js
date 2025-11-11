import { usePage } from '@inertiajs/vue3'

// Devuelve una función asset(path) que antepone la base compartida por Inertia
// para que funcione igual si la app vive en raíz o en subcarpeta.
export function useAssetUrl () {
  const page = usePage()
  const base = page?.props?.globals?.base || ''

  return function asset (p = '') {
    const cleanBase = String(base).replace(/\/$/, '')
    const cleanPath = String(p).replace(/^\/+/, '')
    return `${cleanBase}/${cleanPath}`
  }
}
