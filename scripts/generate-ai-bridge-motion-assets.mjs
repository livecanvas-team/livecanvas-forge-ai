#!/usr/bin/env node

import fs from 'node:fs'
import path from 'node:path'

const DEFAULT_IMAGE_ENDPOINT = 'https://api.wavespeed.ai/api/v3/openai/gpt-image-2/edit'
const DEFAULT_VIDEO_ENDPOINT = 'https://api.wavespeed.ai/api/v3/kwaivgi/kling-v3.0-std/image-to-video'
const DEFAULT_UPLOAD_ENDPOINT = 'https://api.wavespeed.ai/api/v3/media/upload/binary'

const ASSETS = {
  hero: {
    outputBase: 'bridge-mobius-hero-v2',
    imagePrompt: [
      'Use case: style-transfer.',
      'Asset type: wide hero poster for LiveCanvas AI Bridge.',
      'Primary request: preserve the reference as the visual family, but make a subtle original variation of the abstract form.',
      'Subject: one continuous lamellar Mobius ribbon sculpture made from 10 to 12 parallel fins, with a slightly asymmetric double-lobed bridge silhouette and a clear central aperture.',
      'Composition: cinematic 16:9 frame; place the complete sculpture entirely in the right 45 percent of the canvas; preserve generous clean negative space across the left 45 percent for white hero copy; do not crop the sculpture.',
      'Backdrop: perfectly clean near-black plum background (#09070d to #14071d), no scenery.',
      'Material: soft matte satin polymer with layered ribbed edges, tactile and dimensional, never chrome, glass, liquid, or glossy metal.',
      'Lighting and color: cyan #26c6da from the upper right, magenta #ec3e8d from the lower right, restrained violet #712cf9 only where the two lights meet; deep controlled shadows.',
      'Camera: fixed orthographic-like product camera, three-quarter perspective, no depth-of-field blur.',
      'Keep unchanged: abstract sculptural identity, black backdrop, cyan/magenta opposing light, premium minimal rendering.',
      'Change only: silhouette, number and spacing of fins, and right-weighted composition.',
      'Avoid: text, letters, logos, UI, people, particles, bokeh, stars, smoke, extra objects, gradients detached from the sculpture, lens flare, watermark.',
    ].join('\n'),
    videoPrompt: [
      'A single abstract lamellar Mobius ribbon sculpture performs one complete calm sinusoidal motion cycle over exactly ten seconds.',
      'The layered fins breathe outward, twist gently around the central aperture, and settle back into precisely the starting pose at the final frame.',
      'Movement is continuous, slow, fluid, mathematically smooth, and restrained, like a soft ribbon moving under water without turbulence.',
      'Keep the sculpture entirely in the right 45 percent of the frame and keep the left side empty for typography.',
      'The camera, framing, background, focus, exposure, cyan light, magenta light, and material remain absolutely locked.',
      'No cut, no camera move, no zoom, no shape collapse, no new objects. The first and final frames must match for a seamless loop.',
    ].join(' '),
  },
  signal: {
    outputBase: 'bridge-torus-signal-v2',
    imagePrompt: [
      'Use case: style-transfer.',
      'Asset type: supporting visual poster for LiveCanvas AI Bridge.',
      'Primary request: preserve the reference as the visual family, but create a subtle original variation of the abstract ring.',
      'Subject: one stacked toroidal signal ring made from 9 to 11 broad parallel lamellar ribbons, slightly oval and gently offset so the layers read as a connected bridge.',
      'Composition: cinematic 16:9 frame; center the ring slightly right of center with generous dark breathing room around it; keep every edge visible.',
      'Backdrop: perfectly clean near-black plum background (#09070d to #14071d), no scenery.',
      'Material: soft matte rubberized satin, layered and tactile, never chrome, glass, liquid, or glossy metal.',
      'Lighting and color: cyan #26c6da on one outer arc, magenta #ec3e8d on the opposite arc, restrained violet #712cf9 through the inner bridge; deep controlled shadows.',
      'Camera: fixed product camera, slight three-quarter angle, crisp edges, no depth-of-field blur.',
      'Keep unchanged: abstract toroidal family, black backdrop, opposing cyan/magenta lighting, premium minimal rendering.',
      'Change only: oval proportion, ribbon spacing, small rotational offset, and lighting balance.',
      'Avoid: text, letters, logos, UI, people, particles, bokeh, stars, smoke, extra objects, lens flare, watermark.',
    ].join('\n'),
    videoPrompt: [
      'A single layered toroidal signal ring performs one complete calm sinusoidal breathing cycle over exactly ten seconds.',
      'The lamellar ribbons open by a few millimeters, rotate together by a very small angle, pass a soft cyan-to-magenta light pulse around the ring, then return precisely to the starting pose at the final frame.',
      'Motion is slow, fluid, coherent, premium, and understated. The ring remains structurally stable and fully visible.',
      'The camera, framing, background, focus, exposure, and material remain absolutely locked.',
      'No cut, no camera move, no zoom, no shape collapse, no new objects. The first and final frames must match for a seamless loop.',
    ].join(' '),
  },
}

const VIDEO_NEGATIVE_PROMPT = [
  'camera movement',
  'zoom',
  'dolly',
  'pan',
  'tilt',
  'scene change',
  'cut',
  'jump',
  'flicker',
  'jitter',
  'warping background',
  'shape collapse',
  'melting',
  'liquid',
  'chrome',
  'glass',
  'particles',
  'smoke',
  'text',
  'logo',
  'watermark',
  'blur',
  'low quality',
].join(', ')

function parseArgs(argv) {
  const args = {}
  for (let index = 2; index < argv.length; index += 1) {
    const token = argv[index]
    if (!token.startsWith('--')) {
      continue
    }
    const key = token.slice(2)
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
  return path.resolve(value)
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

function ensureDirectory(filePath) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true })
}

async function requestJson(url, options = {}, context = 'WaveSpeed request') {
  const response = await fetch(url, options)
  const text = await response.text()
  if (!response.ok) {
    throw new Error(`${context} failed with HTTP ${response.status}: ${text}`)
  }
  try {
    return JSON.parse(text)
  } catch {
    throw new Error(`${context} returned invalid JSON`)
  }
}

async function uploadMedia(filePath, apiKey) {
  const uploadEndpoint = String(process.env.WAVESPEED_UPLOAD_ENDPOINT || DEFAULT_UPLOAD_ENDPOINT).trim()
  const form = new FormData()
  const bytes = fs.readFileSync(filePath)
  form.append('file', new Blob([bytes], { type: getMimeType(filePath) }), path.basename(filePath))
  const json = await requestJson(uploadEndpoint, {
    method: 'POST',
    headers: { Authorization: `Bearer ${apiKey}` },
    body: form,
  }, 'WaveSpeed media upload')
  const url = json?.data?.download_url || json?.data?.url || json?.download_url || json?.url
  if (!url) {
    throw new Error('WaveSpeed media upload did not return a public URL')
  }
  return url
}

async function submitPrediction(endpoint, payload, apiKey, label) {
  const json = await requestJson(endpoint, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  }, label)
  const task = json?.data || json
  if (!task?.id) {
    throw new Error(`${label} did not return a prediction id`)
  }
  return task.urls?.get || `https://api.wavespeed.ai/api/v3/predictions/${task.id}/result`
}

async function pollPrediction(resultUrl, apiKey, label) {
  const maxAttempts = Number(process.env.WAVESPEED_POLL_ATTEMPTS || 180)
  const intervalMs = Number(process.env.WAVESPEED_POLL_INTERVAL_MS || 2500)
  for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
    if (attempt > 0) {
      await new Promise((resolve) => setTimeout(resolve, intervalMs))
    }
    const json = await requestJson(resultUrl, {
      headers: { Authorization: `Bearer ${apiKey}` },
    }, `${label} polling`)
    const result = json?.data || json
    const status = String(result?.status || '').toLowerCase()
    if (status === 'completed') {
      const outputs = Array.isArray(result.outputs) ? result.outputs : []
      if (!outputs[0]) {
        throw new Error(`${label} completed without an output URL`)
      }
      return { result, outputUrl: outputs[0] }
    }
    if (['failed', 'cancelled', 'timeout'].includes(status)) {
      throw new Error(`${label} ${status}: ${result?.error || 'unknown error'}`)
    }
    if (!['created', 'processing', 'queued'].includes(status)) {
      throw new Error(`${label} returned unexpected status: ${status || 'empty'}`)
    }
  }
  throw new Error(`${label} did not complete before the polling timeout`)
}

async function downloadFile(url, outputPath, label) {
  const response = await fetch(url)
  if (!response.ok) {
    throw new Error(`${label} output download failed with HTTP ${response.status}`)
  }
  ensureDirectory(outputPath)
  fs.writeFileSync(outputPath, Buffer.from(await response.arrayBuffer()))
}

async function generatePoster({ sourcePath, outputPath, asset, apiKey }) {
  const sourceUrl = await uploadMedia(sourcePath, apiKey)
  const imageEndpoint = String(process.env.WAVESPEED_IMAGE_EDIT_ENDPOINT || DEFAULT_IMAGE_ENDPOINT).trim()
  const resultUrl = await submitPrediction(imageEndpoint, {
    images: [sourceUrl],
    prompt: asset.imagePrompt,
    aspect_ratio: '16:9',
    resolution: String(process.env.WAVESPEED_IMAGE_RESOLUTION || '2k'),
    quality: String(process.env.WAVESPEED_IMAGE_QUALITY || 'medium'),
    output_format: 'jpeg',
  }, apiKey, `${asset.outputBase} poster generation`)
  const prediction = await pollPrediction(resultUrl, apiKey, `${asset.outputBase} poster generation`)
  await downloadFile(prediction.outputUrl, outputPath, `${asset.outputBase} poster`)
  return {
    output: outputPath,
    model: imageEndpoint,
    prompt: asset.imagePrompt,
  }
}

async function generateVideo({ posterPath, outputPath, asset, apiKey }) {
  const posterUrl = await uploadMedia(posterPath, apiKey)
  const videoEndpoint = String(process.env.WAVESPEED_VIDEO_ENDPOINT || DEFAULT_VIDEO_ENDPOINT).trim()
  const resultUrl = await submitPrediction(videoEndpoint, {
    image: posterUrl,
    end_image: posterUrl,
    prompt: asset.videoPrompt,
    negative_prompt: VIDEO_NEGATIVE_PROMPT,
    duration: 10,
    cfg_scale: 0.55,
    sound: false,
    shot_type: 'customize',
  }, apiKey, `${asset.outputBase} video generation`)
  const prediction = await pollPrediction(resultUrl, apiKey, `${asset.outputBase} video generation`)
  await downloadFile(prediction.outputUrl, outputPath, `${asset.outputBase} video`)
  return {
    output: outputPath,
    model: videoEndpoint,
    prompt: asset.videoPrompt,
    negativePrompt: VIDEO_NEGATIVE_PROMPT,
    duration: 10,
    firstAndLastFrame: 'same poster',
  }
}

async function main() {
  const args = parseArgs(process.argv)
  const apiKey = String(process.env.WAVESPEED_API_KEY || '').trim()
  if (!apiKey) {
    throw new Error('WAVESPEED_API_KEY is required')
  }

  const outputDir = requireArg(args, 'output-dir')
  const sources = {
    hero: requireArg(args, 'hero-source'),
    signal: requireArg(args, 'signal-source'),
  }
  const stage = String(args.stage || 'all').toLowerCase()
  if (!['all', 'posters', 'videos'].includes(stage)) {
    throw new Error('--stage must be all, posters, or videos')
  }
  for (const sourcePath of Object.values(sources)) {
    if (!fs.existsSync(sourcePath)) {
      throw new Error(`Source image not found: ${sourcePath}`)
    }
  }

  fs.mkdirSync(outputDir, { recursive: true })
  const manifestPath = path.join(outputDir, 'bridge-motion-assets-generation.json')
  let previousManifest = {}
  if (fs.existsSync(manifestPath)) {
    try {
      previousManifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
    } catch {
      previousManifest = {}
    }
  }
  const manifest = {
    generatedAt: new Date().toISOString(),
    provider: 'WaveSpeedAI',
    stage,
    assets: previousManifest.assets || {},
  }

  for (const [key, asset] of Object.entries(ASSETS)) {
    const posterPath = path.join(outputDir, `${asset.outputBase}-poster.jpg`)
    const videoPath = path.join(outputDir, `${asset.outputBase}-loop.mp4`)
    const record = manifest.assets[key] || {}
    if (stage === 'all' || stage === 'posters') {
      process.stdout.write(`Generating ${key} poster...\n`)
      record.poster = await generatePoster({ sourcePath: sources[key], outputPath: posterPath, asset, apiKey })
    }
    if (stage === 'all' || stage === 'videos') {
      if (!fs.existsSync(posterPath)) {
        throw new Error(`Poster required before video generation: ${posterPath}`)
      }
      process.stdout.write(`Generating ${key} video...\n`)
      record.video = await generateVideo({ posterPath, outputPath: videoPath, asset, apiKey })
    }
    manifest.assets[key] = record
  }

  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n')
  process.stdout.write(`${JSON.stringify({ ok: true, outputDir, manifestPath }, null, 2)}\n`)
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`)
  process.exit(1)
})
