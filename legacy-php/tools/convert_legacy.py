import io
import json
import re
import sys


SOURCE = "legacy/data.js"
OUTPUT = "data/sites.json"


def clean_text(value):
    value = value.replace("\u200b", "").replace("\u00a0", " ")
    return re.sub(r"\s+", " ", value).strip()


def category_rules():
    return [
        (
            "ai",
            r"(?<![a-z0-9])ai(?![a-z0-9])|提示词|Prompt|prompt|图像生成|图片生成|生图|视频生成|文生|大模型|智能体|Agent|对话|模型|GPT|chatgpt|midjourney|seedance|即梦|可灵|kling|数字人|deepseek|豆包|RAG|OpenClaw|claw",
        ),
        (
            "dev",
            r"github|gitlab|gitee|开源|源码|源代码|编程|代码|写代码|程序员|Linux|linux|命令行|命令|开发者|开发|API|编辑器|workflow|skill|Codex|codex|本地部署|显卡|token|项目",
        ),
        (
            "design",
            r"设计|配色|调色|颜色|色彩|字体|字帖|图标|插画|绘画|画画|画图|像素|拼豆|3D|3d|图纸|海报|PPT模板|草图|贴纸|灵感|视觉|UI|排版",
        ),
        (
            "game",
            r"游戏|小游戏|桌游|街机|游戏机|模拟器|我的世界|Minecraft|小霸王|网页游戏|游戏地图|游戏资源|单机|怀旧|retro|游戏加速",
        ),
        (
            "media",
            r"视频|电影|影视|MV|音乐|听歌|歌曲|歌|儿歌|动画|动漫|直播|台词|影片|片库|动画片|看片|剧|电视|影音|看世界|环游|地球|影像|音效|音频|短视频|综艺|纪录片",
        ),
        (
            "life",
            r"美食|食谱|吃什么|做饭|做菜|种菜|种植|健康|医学|医疗|中医|健身|锻炼|手工|手作|亲子|儿童|启蒙|育儿|修理|维修|装修|家居|天气|气候|城市|旅行|旅游|驾车|开车|汽车|摩托车|自行车|车|睡眠|生活|监控|航拍|地图|修车|做菜|吃药",
        ),
        (
            "learning",
            r"学习|课程|英语|学英语|听力|单词|外语|慕课|课堂|教育|学校|大学|考试|试卷|四六级|考研|高考|志愿|书|电子书|知识|百科|科普|题库|学术|论文|阅读|打字|语言|文献|历史|写作|读书|单词|考试|学数学|学中文",
        ),
    ]


def classify(name, text, url):
    haystack = f"{name}\n{text}\n{url}".lower()
    for slug, pattern in category_rules():
        if re.search(pattern, haystack, flags=re.IGNORECASE):
            return slug
    return "tool"


def split_name_desc(text):
    lowered = text.lower()
    for marker in ("：", ":"):
        idx = lowered.find(marker)
        if idx > 0:
            name = clean_text(text[:idx])
            desc = clean_text(text[idx + 1 :])
            return name, desc
    return clean_text(text), ""


def build_sites(old):
    sites = []
    for index, item in enumerate(old, 1):
        text = clean_text(item.get("text", ""))
        name, desc = split_name_desc(text)
        links = item.get("links") or []
        if not name and links:
            name = clean_text(links[0].get("label", ""))
        primary_url = ""
        if item.get("url"):
            primary_url = clean_text(item["url"])
        elif links:
            primary_url = clean_text(links[0].get("href", ""))

        unique_links = []
        seen = set()
        for link in links:
            href = clean_text(link.get("href", ""))
            if not href or href in seen:
                continue
            seen.add(href)
            unique_links.append(
                {
                    "label": clean_text(link.get("label", href)),
                    "url": href,
                }
            )

        category = classify(name, text, primary_url)
        sites.append(
            {
                "id": f"am-{index:03d}",
                "name": name,
                "desc": desc or text,
                "url": primary_url,
                "category": category,
                "links": unique_links,
            }
        )
    return sites


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    with io.open(SOURCE, "r", encoding="utf-8") as f:
        content = f.read()
    legacy = json.loads(content[len("window.TOOL_DATA = ") : -2])
    sites = build_sites(legacy["tools"])
    counts = {}
    for site in sites:
        counts[site["category"]] = counts.get(site["category"], 0) + 1
    with io.open(OUTPUT, "w", encoding="utf-8") as f:
        json.dump(
            {
                "meta": {
                    "title": legacy.get("meta", {}).get("title", "工具箱"),
                    "source": legacy.get("meta", {}).get("source", ""),
                    "updated": "2026-09-07",
                },
                "sites": sites,
            },
            f,
            ensure_ascii=False,
            separators=(",", ":"),
        )
    print("sites:", len(sites))
    print("categories:")
    for slug, count in sorted(counts.items(), key=lambda pair: (-pair[1], pair[0])):
        print(f"  {slug}: {count}")


if __name__ == "__main__":
    main()
