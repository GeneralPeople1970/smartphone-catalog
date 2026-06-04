import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const projectRoot = path.resolve(import.meta.dirname, '..');
const sourceDir = path.resolve(projectRoot, process.argv[2] ?? 'storage/app/private/phone-data');

const fallbackBrands = new Map([
  ['xiaomi', '小米'],
]);

try {
  const records = loadPhoneRecords(sourceDir);
  process.stdout.write(JSON.stringify(records));
} catch (error) {
  console.error(error.message);
  process.exit(1);
}

function loadPhoneRecords(directory) {
  if (!fs.existsSync(directory)) {
    throw new Error(`Phone data directory was not found: ${directory}`);
  }

  return fs.readdirSync(directory)
    .filter((file) => file.endsWith('.js') || file.endsWith('.json'))
    .sort((left, right) => left.localeCompare(right, 'zh-Hans-CN'))
    .flatMap((file) => parsePhoneFile(path.join(directory, file)));
}

function parsePhoneFile(filePath) {
  const fileName = path.basename(filePath);
  const extension = path.extname(fileName).toLowerCase();

  if (extension === '.json') {
    try {
      const records = JSON.parse(fs.readFileSync(filePath, 'utf8').replace(/^\uFEFF/, ''));

      if (!Array.isArray(records)) {
        throw new Error('the JSON root is not an array');
      }

      return records.map((record, index) => normalizeRecord(record, fileName, index));
    } catch (error) {
      throw new Error(`Failed to parse ${fileName}: ${error.message}`);
    }
  }

  const context = {};

  try {
    vm.runInNewContext(toRunnableModule(fs.readFileSync(filePath, 'utf8')), context, {
      filename: fileName,
    });
  } catch (error) {
    throw new Error(`Failed to parse ${fileName}: ${error.message}`);
  }

  const records = Object.values(context).find(Array.isArray);

  if (!records) {
    throw new Error(`No phone array was found in ${fileName}`);
  }

  return records.map((record, index) => normalizeRecord(record, fileName, index));
}

function toRunnableModule(source) {
  return source
    .replace(/export\s+default\s+\w+\s*;?/g, '')
    .replace(/\bconst\s+([A-Za-z_$][\w$]*)\s*=\s*\[/, 'var $1 = [')
    .replace(/\blet\s+([A-Za-z_$][\w$]*)\s*=\s*\[/, 'var $1 = [');
}

function normalizeRecord(record, fileName, index) {
  const sourceName = path.basename(fileName, '.js');
  const brand = cleanText(record.company ?? fallbackBrands.get(sourceName) ?? sourceName);
  const name = cleanText(record.phonename ?? record.name ?? `${brand}-${index + 1}`);
  const sourceId = record.id ?? index + 1;

  return {
    source_key: hashSourceKey(fileName, sourceId, name),
    source_file: fileName,
    source_id: String(sourceId),
    brand,
    name,
    slug: null,
    image_url: cleanText(record.imgurl ?? record.image ?? ''),
    price: normalizePrice(record.price),
    soc_name: cleanText(record.socname ?? record.processor ?? ''),
    battery_capacity: normalizeBattery(record.battery),
    specs: record,
  };
}

function hashSourceKey(fileName, sourceId, name) {
  return crypto
    .createHash('sha1')
    .update(`${fileName}|${sourceId}|${name}`)
    .digest('hex');
}

function cleanText(value) {
  return value === null || value === undefined ? '' : String(value).trim();
}

function normalizePrice(value) {
  const price = cleanText(value);

  return price === '' ? null : price;
}

function normalizeBattery(value) {
  if (Number.isInteger(value)) {
    return value;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return Math.trunc(value);
  }

  const match = String(value ?? '').match(/(\d{3,5})\s*mAh/i);

  return match ? Number.parseInt(match[1], 10) : null;
}
