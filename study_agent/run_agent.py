import sys, json
from agents import build_note_graph, NoteAgentState
from langchain_core.runnables import RunnableConfig

if __name__ == "__main__":
    initial: NoteAgentState = json.load(sys.stdin)
    app = build_note_graph()
    result = app.invoke(initial, RunnableConfig(parallel=True))
    print(json.dumps(result))
