/**
 * 将字符串转换为URL友好的“slug”格式。
 * 例如：“Example Phone A” -> “example-phone-a”
 * @param {string} text - 要转换的字符串。
 * @returns {string} - 转换后的slug。
 */
export function slugify(text) {
  return text
    .toString()
    .normalize('NFD') // 分解重音字符
    .replace(/[\u0300-\u036f]/g, '') // 移除重音符号
    .toLowerCase() // 转换为小写
    .trim() // 移除首尾空格
    .replace(/\s+/g, '-') // 将空格替换为连字符
    .replace(/[^\w-\u4e00-\u9fa5]/g, '') // 移除所有非单词字符（字母、数字、下划线）和非连字符
    .replace(/--+/g, '-') // 将多个连字符替换为单个连字符
}
