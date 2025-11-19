FROM node:20-alpine

WORKDIR /app

# Copy package files first for better caching
COPY package*.json ./

# Install dependencies during build
RUN npm install

# Copy application files
COPY . .

EXPOSE 3000

# Simple CMD - entrypoint will be handled by docker-compose or we can add it later
CMD ["npm", "run", "dev", "--", "--host"]

