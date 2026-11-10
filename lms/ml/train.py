"""Train and evaluate the initial LMS risk model."""
import csv
import json
from pathlib import Path

try:
    from .generate_mock_data import generate
    from .risk_model import LogisticRiskModel, evaluate, save_model, train_test_split
except ImportError:
    from generate_mock_data import generate
    from risk_model import LogisticRiskModel, evaluate, save_model, train_test_split

ROOT = Path(__file__).parent
DATA = ROOT / "data" / "mock_learning_events.csv"
MODEL = ROOT / "artifacts" / "risk_model.json"
REPORT = ROOT / "artifacts" / "evaluation.json"


def load_rows():
    if not DATA.exists():
        generate(DATA)
    with DATA.open(newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))
    for row in rows:
        for key in row:
            if key != "student_id":
                row[key] = float(row[key]) if key == "progress_ratio" or key == "average_score" else int(float(row[key]))
    return rows


def main():
    rows = load_rows()
    train, test = train_test_split(rows)
    model = LogisticRiskModel().fit(train)
    report = {"train": len(train), "test": evaluate(model, test), "features": model.to_dict()["features"]}
    MODEL.parent.mkdir(parents=True, exist_ok=True)
    save_model(model, MODEL)
    REPORT.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
