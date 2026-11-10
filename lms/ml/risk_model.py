"""Small, dependency-free risk model for LMS experimentation.

The model is deliberately replaceable: the API contract can later be backed by
scikit-learn or an external provider without changing the PHP integration.
"""
import json
import math
import random
from pathlib import Path

FEATURES = [
    "days_since_login",
    "progress_ratio",
    "average_score",
    "attempt_count",
    "content_minutes",
    "forum_questions",
]


def sigmoid(value):
    value = max(-50.0, min(50.0, value))
    return 1.0 / (1.0 + math.exp(-value))


def _vector(row):
    return [
        float(row["days_since_login"]) / 14.0,
        float(row["progress_ratio"]),
        float(row["average_score"]) / 100.0,
        float(row["attempt_count"]) / 8.0,
        float(row["content_minutes"]) / 240.0,
        float(row["forum_questions"]) / 5.0,
    ]


class LogisticRiskModel:
    def __init__(self, weights=None, bias=0.0):
        self.weights = list(weights or [0.0] * len(FEATURES))
        self.bias = float(bias)

    def fit(self, rows, learning_rate=0.35, epochs=900):
        for _ in range(epochs):
            gradients = [0.0] * len(self.weights)
            bias_gradient = 0.0
            for row in rows:
                prediction = self.predict_probability(row)
                error = prediction - float(row["risk_label"])
                values = _vector(row)
                for index, value in enumerate(values):
                    gradients[index] += error * value
                bias_gradient += error
            size = float(len(rows))
            for index in range(len(self.weights)):
                self.weights[index] -= learning_rate * gradients[index] / size
            self.bias -= learning_rate * bias_gradient / size
        return self

    def predict_probability(self, row):
        values = _vector(row)
        return sigmoid(self.bias + sum(weight * value for weight, value in zip(self.weights, values)))

    def predict(self, row, threshold=0.5):
        probability = self.predict_probability(row)
        return {
            "risk_score": round(probability, 6),
            "risk_level": "high" if probability >= threshold else ("medium" if probability >= 0.35 else "low"),
        }

    def to_dict(self):
        return {"features": FEATURES, "weights": self.weights, "bias": self.bias}

    @classmethod
    def from_dict(cls, payload):
        return cls(payload["weights"], payload["bias"])


def train_test_split(rows, test_ratio=0.25, seed=42):
    data = list(rows)
    random.Random(seed).shuffle(data)
    split = max(1, int(len(data) * (1.0 - test_ratio)))
    return data[:split], data[split:]


def evaluate(model, rows, threshold=0.35):
    actual = [int(row["risk_label"]) for row in rows]
    predicted = [int(model.predict(row, threshold)["risk_score"] >= threshold) for row in rows]
    tp = sum(a == 1 and p == 1 for a, p in zip(actual, predicted))
    tn = sum(a == 0 and p == 0 for a, p in zip(actual, predicted))
    fp = sum(a == 0 and p == 1 for a, p in zip(actual, predicted))
    fn = sum(a == 1 and p == 0 for a, p in zip(actual, predicted))
    precision = tp / float(tp + fp) if tp + fp else 0.0
    recall = tp / float(tp + fn) if tp + fn else 0.0
    f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
    return {
        "samples": len(rows),
        "accuracy": round((tp + tn) / float(len(rows)), 4),
        "precision": round(precision, 4),
        "recall": round(recall, 4),
        "f1": round(f1, 4),
        "confusion_matrix": {"true_positive": tp, "true_negative": tn, "false_positive": fp, "false_negative": fn},
    }


def save_model(model, path):
    Path(path).write_text(json.dumps(model.to_dict(), indent=2), encoding="utf-8")


def load_model(path):
    return LogisticRiskModel.from_dict(json.loads(Path(path).read_text(encoding="utf-8")))
