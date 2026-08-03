function applyHttpBasicAuth(headers, config = {}) {
  const username = String(config.httpBasicUsername || '')
  const password = String(config.httpBasicPassword || '')

  if (!username || !password) {
    return headers
  }

  headers.Authorization = `Basic ${Buffer.from(`${username}:${password}`, 'utf8').toString('base64')}`
  return headers
}

module.exports = {
  applyHttpBasicAuth
}
