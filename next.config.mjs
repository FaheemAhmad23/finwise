/** @type {import('next').NextConfig} */
const nextConfig = {
  typescript: {
    // Types are validated — turn this off to see TS errors during development
    ignoreBuildErrors: false,
  },
  images: {
    unoptimized: true,
  },
  // Required for self-hosted Docker deployment (not needed on Vercel)
  output: 'standalone',
};

export default nextConfig;
