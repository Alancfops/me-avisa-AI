import { useEffect, useState } from 'react'

const apiBase = import.meta.env.VITE_API_URL ?? 'http://localhost:3000'

function App() {
  const [status, setStatus] = useState<string>('…')

  useEffect(() => {
    const ac = new AbortController()
    fetch(`${apiBase}/api/v1/health`, { signal: ac.signal })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((j) => setStatus(`${j.status} — ${j.service}`))
      .catch(() => setStatus('API indisponível (subir Rails em :3000?)'))
    return () => ac.abort()
  }, [])

  return (
    <div className="min-h-dvh flex flex-col items-center justify-center gap-4 px-4 bg-slate-950 text-slate-100">
      <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">me_avisaAI</h1>
      <p className="text-center text-sm text-slate-400 max-w-md sm:text-base">
        Front React + Tailwind (mobile-first). Consome apenas a API em{' '}
        <code className="rounded bg-slate-800 px-1 py-0.5 text-slate-200">{apiBase}</code>
      </p>
      <p className="rounded-lg border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-mono text-emerald-300">
        {status}
      </p>
    </div>
  )
}

export default App
