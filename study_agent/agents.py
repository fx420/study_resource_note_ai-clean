import sys, json, os
import requests

def build_prompt(md, materials):
    return f"""
You are an AI study‐note generator.
Metadata:
- Education Level: {md['education_level']}
- Course: {md['course']}
- Topic: {md['topic']}
- Note Level: {md['note_level']}
- Learning Goals: {md['learning_goals']}
- Prior Knowledge: {md['prior_knowledge']}
- Preferred Format: {md['preferred_format']}
- Example Count/Difficulty: {md['example_count']}

Materials:
{materials}

Generate study notes with this structure:
1. Overview (paragraph)
2. Key Points (point form)
3. Examples (point form)
4. Summary (paragraph)
"""

def main():
    payload    = json.load(sys.stdin)
    metadata   = payload["metadata"]
    file_paths = payload.get("files", [])

    contents = []
    for path in file_paths:
        try:
            with open(path, encoding="utf-8") as f:
                contents.append(f.read())
        except FileNotFoundError:
            pass
    all_materials = "\n\n".join(contents)

    prompt = build_prompt(metadata, all_materials)

    api_key = os.getenv("OPENROUTER_API_KEY")
    if not api_key:
        raise RuntimeError("OPENROUTER_API_KEY is not set in environment")

    url = "https://openrouter.ai/api/v1/chat/completions"
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type":  "application/json",
    }
    body = {
        "model": "deepseek/deepseek-r1:free",
        "messages": [{"role": "system", "content": prompt}],
        "temperature": 0.3,
        "max_tokens": 1024,
    }

    resp = requests.post(url, headers=headers, json=body)
    resp.raise_for_status()
    data = resp.json()
    generated = data["choices"][0]["message"]["content"]

    print(json.dumps({"generated_notes": generated}))

if __name__ == "__main__":
    main()
