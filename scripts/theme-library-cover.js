#!/usr/bin/env node

const fs = require('node:fs')
const path = require('node:path')

function parseArgs(argv) {
  const args = {}
  for (let index = 2; index < argv.length; index += 1) {
    const value = argv[index]
    if (!value.startsWith('--')) {
      continue
    }

    const key = value.slice(2)
    const next = argv[index + 1]
    if (!next || next.startsWith('--')) {
      args[key] = true
      continue
    }

    args[key] = next
    index += 1
  }

  return args
}

function requireArg(args, key) {
  const value = String(args[key] || '').trim()
  if (!value) {
    throw new Error(`Missing required --${key}`)
  }

  return value
}

function getMimeType(filePath) {
  const extension = path.extname(filePath).toLowerCase()
  if (extension === '.jpg' || extension === '.jpeg') {
    return 'image/jpeg'
  }
  if (extension === '.webp') {
    return 'image/webp'
  }

  return 'image/png'
}

function buildPrompt({ title, subtitle, style }) {
  return [
    'Create a premium marketplace template cover image from the provided website screenshot.',
    'Keep the screenshot readable as the main subject, inside a polished desktop browser or monitor mockup.',
    'Generate an abstract, high-end background behind it, using colors that harmonize with the screenshot.',
    'The final image should feel like a Framer marketplace template card: editorial, dramatic, polished, and product-focused.',
    'Use soft shadows, tasteful reflections or glass/metal shapes, and strong visual depth.',
    'Do not invent unrelated UI. Do not change the website layout inside the screenshot. Do not add fake metrics, likes, comments, people, watermarks, or brand logos.',
    'Avoid adding large text unless it is already visible inside the screenshot.',
    `Template name: ${title}.`,
    subtitle ? `Short positioning: ${subtitle}.` : '',
    style ? `Visual direction: ${style}.` : 'Visual direction: dark premium gallery card, abstract tuned background, readable central screenshot.',
    'Output a single landscape cover suitable for a theme marketplace grid.',
  ].filter(Boolean).join('\n')
}

function writeFileEnsuringDirectory(filePath, buffer) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true })
  fs.writeFileSync(filePath, buffer)
}

async function savePromptOnly({ output, prompt, provider, screenshot, title, subtitle, style }) {
  const payload = {
    ok: true,
    mode: 'prompt',
    provider,
    screenshot,
    title,
    subtitle,
    style,
    prompt,
    next_steps: [
      'Run with --provider openai and OPENAI_API_KEY to generate through GPT Image 2.',
      'Run with --provider wavespeed and WAVESPEED_IMAGE_EDIT_ENDPOINT/WAVESPEED_API_KEY to route through WaveSpeed or another image platform.',
      'Store the generated file as themes/{theme-slug}/screenshots/cover.jpg and point catalog.screenshot to it.',
    ],
  }

  writeFileEnsuringDirectory(output, Buffer.from(JSON.stringify(payload, null, 2) + '\n'))
  return payload
}

async function callOpenAI({ screenshot, output, prompt, size, quality }) {
  const apiKey = String(process.env.OPENAI_API_KEY || '').trim()
  if (!apiKey) {
    throw new Error('OPENAI_API_KEY is required for --provider openai')
  }

  const form = new FormData()
  const imageBytes = fs.readFileSync(screenshot)
  const blob = new Blob([imageBytes], { type: getMimeType(screenshot) })

  form.append('model', String(process.env.LCFA_OPENAI_IMAGE_MODEL || 'gpt-image-2'))
  form.append('image[]', blob, path.basename(screenshot))
  form.append('prompt', prompt)
  form.append('size', size)
  form.append('quality', quality)
  form.append('output_format', path.extname(output).toLowerCase() === '.webp' ? 'webp' : 'jpeg')
  form.append('output_compression', '90')

  const response = await fetch('https://api.openai.com/v1/images/edits', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
    },
    body: form,
  })

  const text = await response.text()
  if (!response.ok) {
    throw new Error(`OpenAI image edit failed with HTTP ${response.status}: ${text}`)
  }

  const json = JSON.parse(text)
  const base64 = json?.data?.[0]?.b64_json
  if (!base64) {
    throw new Error('OpenAI image edit response did not include data[0].b64_json')
  }

  writeFileEnsuringDirectory(output, Buffer.from(base64, 'base64'))
  return {
    ok: true,
    provider: 'openai',
    model: String(process.env.LCFA_OPENAI_IMAGE_MODEL || 'gpt-image-2'),
    output,
    size,
    quality,
  }
}

async function callWaveSpeed({ screenshot, output, prompt, size, quality }) {
  const endpoint = String(process.env.WAVESPEED_IMAGE_EDIT_ENDPOINT || 'https://api.wavespeed.ai/api/v3/openai/gpt-image-2/edit').trim()
  const uploadEndpoint = String(process.env.WAVESPEED_UPLOAD_ENDPOINT || 'https://api.wavespeed.ai/api/v3/media/upload/binary').trim()
  const apiKey = String(process.env.WAVESPEED_API_KEY || '').trim()
  if (!endpoint || !uploadEndpoint || !apiKey) {
    throw new Error('WAVESPEED_API_KEY is required for --provider wavespeed')
  }

  const uploadForm = new FormData()
  const sourceBytes = fs.readFileSync(screenshot)
  uploadForm.append('file', new Blob([sourceBytes], { type: getMimeType(screenshot) }), path.basename(screenshot))

  const uploadResponse = await fetch(uploadEndpoint, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
    },
    body: uploadForm,
  })

  const uploadText = await uploadResponse.text()
  if (!uploadResponse.ok) {
    throw new Error(`WaveSpeed upload failed with HTTP ${uploadResponse.status}: ${uploadText}`)
  }

  const uploadJson = JSON.parse(uploadText)
  const imageUrl = uploadJson?.data?.download_url || uploadJson?.data?.url || uploadJson?.download_url || uploadJson?.url
  if (!imageUrl) {
    throw new Error('WaveSpeed upload response did not include data.download_url')
  }

  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      prompt,
      images: [imageUrl],
      aspect_ratio: sizeToAspectRatio(size),
      resolution: String(process.env.WAVESPEED_RESOLUTION || '1k'),
      quality: String(process.env.WAVESPEED_QUALITY || quality),
      output_format: path.extname(output).toLowerCase() === '.webp' ? 'webp' : 'jpeg',
      enable_sync_mode: String(process.env.WAVESPEED_SYNC_MODE || 'false') === 'true',
    }),
  })

  const text = await response.text()
  if (!response.ok) {
    throw new Error(`WaveSpeed image edit failed with HTTP ${response.status}: ${text}`)
  }

  const json = JSON.parse(text)
  const data = json?.data || {}
  const pollUrl = data?.urls?.get || data?.url || ''
  let finalData = data
  if (pollUrl && !hasWaveSpeedOutput(finalData)) {
    finalData = await pollWaveSpeedResult({ pollUrl, apiKey })
  }

  const base64 = finalData?.[0]?.b64_json || finalData?.b64_json || json?.b64_json || finalData?.image_base64
  const outputs = Array.isArray(finalData?.outputs) ? finalData.outputs : []
  const url = finalData?.[0]?.url || finalData?.url || json?.url || finalData?.image_url || outputs[0]

  if (base64) {
    writeFileEnsuringDirectory(output, Buffer.from(base64, 'base64'))
  } else if (url) {
    const image = await fetch(url)
    if (!image.ok) {
      throw new Error(`WaveSpeed returned an image URL that could not be downloaded: HTTP ${image.status}`)
    }
    writeFileEnsuringDirectory(output, Buffer.from(await image.arrayBuffer()))
  } else {
    throw new Error('WaveSpeed response did not include a supported image field')
  }

  return {
    ok: true,
    provider: 'wavespeed',
    output,
    size,
    quality,
    image_url: imageUrl,
    status: finalData?.status || data?.status || 'unknown',
  }
}

function sizeToAspectRatio(size) {
  const match = String(size || '').match(/^(\d+)x(\d+)$/)
  if (!match) {
    return '16:9'
  }

  const width = Number(match[1])
  const height = Number(match[2])
  if (!width || !height) {
    return '16:9'
  }

  const divisor = gcd(width, height)
  return `${width / divisor}:${height / divisor}`
}

function gcd(a, b) {
  while (b) {
    const next = a % b
    a = b
    b = next
  }
  return a
}

function hasWaveSpeedOutput(data) {
  if (!data || typeof data !== 'object') {
    return false
  }

  return Boolean(
    data.b64_json
      || data.image_base64
      || data.url
      || data.image_url
      || (Array.isArray(data.outputs) && data.outputs.length > 0)
  )
}

async function pollWaveSpeedResult({ pollUrl, apiKey }) {
  const maxAttempts = Number(process.env.WAVESPEED_POLL_ATTEMPTS || 60)
  const intervalMs = Number(process.env.WAVESPEED_POLL_INTERVAL_MS || 2000)
  for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
    if (attempt > 0) {
      await new Promise((resolve) => setTimeout(resolve, intervalMs))
    }

    const response = await fetch(pollUrl, {
      headers: {
        Authorization: `Bearer ${apiKey}`,
      },
    })
    const text = await response.text()
    if (!response.ok) {
      throw new Error(`WaveSpeed result polling failed with HTTP ${response.status}: ${text}`)
    }

    const json = JSON.parse(text)
    const data = json?.data || {}
    if (String(data.status || '').toLowerCase() === 'failed') {
      throw new Error(`WaveSpeed task failed: ${data.error || 'unknown error'}`)
    }
    if (hasWaveSpeedOutput(data)) {
      return data
    }
  }

  throw new Error('WaveSpeed task did not complete before polling timed out')
}

async function main() {
  const args = parseArgs(process.argv)
  const screenshot = path.resolve(requireArg(args, 'screenshot'))
  const output = path.resolve(requireArg(args, 'output'))
  const title = requireArg(args, 'title')
  const subtitle = String(args.subtitle || '').trim()
  const style = String(args.style || '').trim()
  const provider = String(args.provider || 'prompt').trim().toLowerCase()
  const size = String(args.size || '1536x1024').trim()
  const quality = String(args.quality || 'low').trim()

  if (!fs.existsSync(screenshot)) {
    throw new Error(`Screenshot not found: ${screenshot}`)
  }

  const prompt = buildPrompt({ title, subtitle, style })
  let result
  if (provider === 'openai') {
    result = await callOpenAI({ screenshot, output, prompt, size, quality })
  } else if (provider === 'wavespeed') {
    result = await callWaveSpeed({ screenshot, output, prompt, size, quality })
  } else if (provider === 'prompt') {
    result = await savePromptOnly({ output, prompt, provider, screenshot, title, subtitle, style })
  } else {
    throw new Error(`Unsupported provider: ${provider}`)
  }

  process.stdout.write(JSON.stringify({ ...result, prompt }, null, 2) + '\n')
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`)
  process.exit(1)
})
