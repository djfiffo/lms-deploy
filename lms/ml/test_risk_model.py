import unittest
from pathlib import Path

try:
    from .generate_mock_data import generate
    from .risk_model import LogisticRiskModel, evaluate, train_test_split
except ImportError:
    from generate_mock_data import generate
    from risk_model import LogisticRiskModel, evaluate, train_test_split


class RiskModelTests(unittest.TestCase):
    def test_model_beats_majority_baseline_on_mock_data(self):
        path = Path(__file__).parent / "test_mock_learning_events.csv"
        rows = generate(path, rows=400, seed=7)
        train, test = train_test_split(rows, seed=7)
        model = LogisticRiskModel().fit(train)
        report = evaluate(model, test)
        majority = max(sum(row["risk_label"] for row in test), len(test) - sum(row["risk_label"] for row in test)) / len(test)
        self.assertGreaterEqual(report["accuracy"], majority)
        self.assertGreaterEqual(report["recall"], 0.7)
        self.assertGreater(report["f1"], 0.6)
        path.unlink(missing_ok=True)


if __name__ == "__main__":
    unittest.main()
