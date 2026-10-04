PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    project_lead TEXT NOT NULL DEFAULT '',
    rollout_lead TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rollout_objects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    start_date TEXT,
    end_date TEXT,
    functional_class TEXT CHECK(functional_class IN ('A','B','C','D') OR functional_class IS NULL),
    technical_class INTEGER CHECK(technical_class IN (1,2,3) OR technical_class IS NULL),
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, name),
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL CHECK(type IN ('RV','FI','DSV')),
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS provider_class_levels (
    provider_type TEXT NOT NULL CHECK(provider_type IN ('FI','DSV')),
    class_code TEXT NOT NULL,
    class_label TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    PRIMARY KEY(provider_type, class_code),
    UNIQUE(provider_type, sort_order)
);

CREATE TABLE IF NOT EXISTS rollout_object_provider_classes (
    rollout_object_id INTEGER NOT NULL,
    provider_type TEXT NOT NULL CHECK(provider_type IN ('FI','DSV')),
    class_code TEXT NOT NULL,
    PRIMARY KEY(rollout_object_id, provider_type),
    FOREIGN KEY(rollout_object_id) REFERENCES rollout_objects(id) ON DELETE CASCADE,
    FOREIGN KEY(provider_type, class_code) REFERENCES provider_class_levels(provider_type, class_code)
);

CREATE TABLE IF NOT EXISTS support_services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    is_standard INTEGER NOT NULL DEFAULT 1,
    project_id INTEGER,
    mandatory_for_all INTEGER NOT NULL DEFAULT 0,
    always_included INTEGER NOT NULL DEFAULT 0,
    schedule_relevant INTEGER NOT NULL DEFAULT 1,
    active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK(is_standard IN (0,1)),
    CHECK(mandatory_for_all IN (0,1)),
    CHECK(always_included IN (0,1)),
    CHECK(schedule_relevant IN (0,1)),
    CHECK(active IN (0,1)),
    CHECK(
        (is_standard=1 AND project_id IS NULL)
        OR (is_standard=0 AND project_id IS NOT NULL)
    ),
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS support_service_classes (
    support_service_id INTEGER NOT NULL,
    dimension TEXT NOT NULL CHECK(dimension IN ('functional','technical','dsv')),
    class_code TEXT NOT NULL,
    PRIMARY KEY(support_service_id, dimension, class_code),
    FOREIGN KEY(support_service_id) REFERENCES support_services(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS support_service_provider_targets (
    support_service_id INTEGER NOT NULL,
    provider_type TEXT NOT NULL CHECK(provider_type IN ('RV','FI','DSV')),
    PRIMARY KEY(support_service_id, provider_type),
    FOREIGN KEY(support_service_id) REFERENCES support_services(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_services (
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id, support_service_id),
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY(support_service_id) REFERENCES support_services(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_service_rollout_objects (
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    rollout_object_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id, support_service_id, rollout_object_id),
    FOREIGN KEY(project_id, support_service_id) REFERENCES project_services(project_id, support_service_id) ON DELETE CASCADE,
    FOREIGN KEY(project_id, rollout_object_id) REFERENCES rollout_objects(project_id, id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_support_matrix (
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    offered INTEGER NOT NULL DEFAULT 0,
    automatic INTEGER NOT NULL DEFAULT 0,
    delivery_mode TEXT NOT NULL DEFAULT '' CHECK(delivery_mode IN ('','self','other_rv','external','none')),
    delivery_partner TEXT NOT NULL DEFAULT '',
    schedule TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id, support_service_id, provider_id),
    FOREIGN KEY(project_id, support_service_id) REFERENCES project_services(project_id, support_service_id) ON DELETE CASCADE,
    FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rollout_object_support_matrix (
    rollout_object_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    support_service_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    offered INTEGER NOT NULL DEFAULT 0,
    automatic INTEGER NOT NULL DEFAULT 0,
    schedule TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(rollout_object_id, support_service_id, provider_id),
    FOREIGN KEY(project_id, support_service_id) REFERENCES project_services(project_id, support_service_id) ON DELETE CASCADE,
    FOREIGN KEY(project_id, rollout_object_id) REFERENCES rollout_objects(project_id, id) ON DELETE CASCADE,
    FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS catalog_service_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_type TEXT NOT NULL CHECK(provider_type IN ('FI','DSV')),
    provider_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    rollout_object_id INTEGER NOT NULL,
    proposed_name TEXT NOT NULL,
    class_code TEXT NOT NULL,
    offered INTEGER NOT NULL DEFAULT 0,
    schedule TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','accepted','rejected')),
    accepted_service_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY(project_id, rollout_object_id) REFERENCES rollout_objects(project_id, id) ON DELETE CASCADE,
    FOREIGN KEY(accepted_service_id) REFERENCES support_services(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS project_provider_contacts (
    project_id INTEGER NOT NULL,
    provider_id INTEGER NOT NULL,
    contact_name TEXT NOT NULL DEFAULT '',
    contact_phone TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(project_id, provider_id),
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY(provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rollout_project ON rollout_objects(project_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_rollout_project_id ON rollout_objects(project_id, id);
CREATE INDEX IF NOT EXISTS idx_project_name ON projects(name);
CREATE INDEX IF NOT EXISTS idx_rollout_name ON rollout_objects(name);
CREATE INDEX IF NOT EXISTS idx_service_project ON support_services(project_id, is_standard, active);
CREATE INDEX IF NOT EXISTS idx_service_catalog ON support_services(is_standard, active, name);
CREATE INDEX IF NOT EXISTS idx_service_class_lookup ON support_service_classes(dimension, class_code, support_service_id);
CREATE INDEX IF NOT EXISTS idx_project_service ON project_services(support_service_id, project_id);
CREATE INDEX IF NOT EXISTS idx_service_rollout_object ON project_service_rollout_objects(rollout_object_id, support_service_id);
CREATE INDEX IF NOT EXISTS idx_service_provider_target ON support_service_provider_targets(provider_type, support_service_id);
CREATE INDEX IF NOT EXISTS idx_project_matrix_service ON project_support_matrix(support_service_id, provider_id);
CREATE INDEX IF NOT EXISTS idx_object_provider_class ON rollout_object_provider_classes(provider_type, class_code, rollout_object_id);
CREATE INDEX IF NOT EXISTS idx_object_matrix_service ON rollout_object_support_matrix(support_service_id, provider_id, rollout_object_id);
CREATE INDEX IF NOT EXISTS idx_catalog_suggestion_status ON catalog_service_suggestions(status, provider_type, created_at);

CREATE TRIGGER IF NOT EXISTS validate_project_service_insert
BEFORE INSERT ON project_services
WHEN EXISTS (
    SELECT 1 FROM support_services s
    WHERE s.id=NEW.support_service_id AND s.is_standard=0 AND s.project_id<>NEW.project_id
)
BEGIN
    SELECT RAISE(ABORT, 'Zusätzliche Leistung gehört zu einem anderen Projekt');
END;

CREATE TRIGGER IF NOT EXISTS validate_project_service_update
BEFORE UPDATE ON project_services
WHEN EXISTS (
    SELECT 1 FROM support_services s
    WHERE s.id=NEW.support_service_id AND s.is_standard=0 AND s.project_id<>NEW.project_id
)
BEGIN
    SELECT RAISE(ABORT, 'Zusätzliche Leistung gehört zu einem anderen Projekt');
END;

CREATE TRIGGER IF NOT EXISTS validate_project_matrix_provider_insert
BEFORE INSERT ON project_support_matrix
WHEN NOT EXISTS (
    SELECT 1 FROM providers p
    JOIN support_service_provider_targets t ON t.provider_type=p.type
    WHERE p.id=NEW.provider_id AND t.support_service_id=NEW.support_service_id
)
BEGIN
    SELECT RAISE(ABORT, 'Leistung ist diesem Leistungserbringer nicht zugeordnet');
END;

CREATE TRIGGER IF NOT EXISTS validate_project_matrix_provider_update
BEFORE UPDATE ON project_support_matrix
WHEN NOT EXISTS (
    SELECT 1 FROM providers p
    JOIN support_service_provider_targets t ON t.provider_type=p.type
    WHERE p.id=NEW.provider_id AND t.support_service_id=NEW.support_service_id
)
BEGIN
    SELECT RAISE(ABORT, 'Leistung ist diesem Leistungserbringer nicht zugeordnet');
END;

PRAGMA user_version = 23;
