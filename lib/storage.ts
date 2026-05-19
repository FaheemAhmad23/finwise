'use client';

// IndexedDB storage layer for offline functionality
export interface StorageKey {
  expenses: 'expenses';
  debts: 'debts';
  people: 'people';
  bills: 'bills';
  settings: 'settings';
}

const DB_NAME = 'finwise-db';
const DB_VERSION = 1;
const STORE_NAMES = ['expenses', 'debts', 'people', 'bills', 'settings'];

let db: IDBDatabase | null = null;

export async function initDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    if (db) {
      resolve(db);
      return;
    }

    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      db = request.result;
      resolve(db);
    };

    request.onupgradeneeded = (event) => {
      const database = (event.target as IDBOpenDBRequest).result;

      // Create object stores if they don't exist
      STORE_NAMES.forEach((storeName) => {
        if (!database.objectStoreNames.contains(storeName)) {
          database.createObjectStore(storeName, { keyPath: 'id' });
        }
      });
    };
  });
}

export async function saveData<T extends { id: string }>(
  storeName: keyof StorageKey,
  data: T
): Promise<void> {
  const database = await initDB();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.put(data);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve();
  });
}

export async function getData<T>(
  storeName: keyof StorageKey,
  id: string
): Promise<T | undefined> {
  const database = await initDB();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction([storeName], 'readonly');
    const store = transaction.objectStore(storeName);
    const request = store.get(id);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

export async function getAllData<T>(storeName: keyof StorageKey): Promise<T[]> {
  const database = await initDB();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction([storeName], 'readonly');
    const store = transaction.objectStore(storeName);
    const request = store.getAll();

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result || []);
  });
}

export async function deleteData(
  storeName: keyof StorageKey,
  id: string
): Promise<void> {
  const database = await initDB();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.delete(id);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve();
  });
}

export async function clearStore(storeName: keyof StorageKey): Promise<void> {
  const database = await initDB();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.clear();

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve();
  });
}

export async function exportData(): Promise<{
  expenses: any[];
  debts: any[];
  people: any[];
  bills: any[];
  settings: any[];
}> {
  const database = await initDB();
  const result: any = {};

  for (const storeName of STORE_NAMES) {
    result[storeName] = await getAllData(storeName);
  }

  return result;
}

export async function importData(data: any): Promise<void> {
  const database = await initDB();

  for (const storeName of STORE_NAMES) {
    if (data[storeName] && Array.isArray(data[storeName])) {
      const transaction = database.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);

      // Clear existing data
      await new Promise<void>((resolve, reject) => {
        const clearRequest = store.clear();
        clearRequest.onerror = () => reject(clearRequest.error);
        clearRequest.onsuccess = () => resolve();
      });

      // Import new data
      for (const item of data[storeName]) {
        await new Promise<void>((resolve, reject) => {
          const request = store.put(item);
          request.onerror = () => reject(request.error);
          request.onsuccess = () => resolve();
        });
      }
    }
  }
}
