FROM node:20-slim

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

COPY webpack.config.js tsconfig.json ./
COPY assets/ ./assets/

CMD ["npm", "run", "watch"]
