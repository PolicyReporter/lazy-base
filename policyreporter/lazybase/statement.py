import psycopg2, json, inspect
from psycopg2.extras import RealDictCursor

class AbstractIterator:
    def __init__(self):
        self.transformations = []
        self.index = -1
        self.rawIndex = -1
        self.columnNames = None
        self.__current = None

    def __iter__(self):
        return self

    def __next__(self):
        self.__current = None
        self.rawIndex += 1
        self.index = self.rawIndex
        current = self.current()
        return self.index, current

    def items(self):
        current = self.current()
        return self.index, current

    def map(self, function):
        self.transformations.append(function)
        return self

    # I chose to implement this one first... before realizing that it's actually done automatically
    def mapJsonDecode(self, *columns):
        def transform(row):
            for column in columns:
                row[column] = json.loads(row[column])
            return row
        return self.map(transform)

    def anonymousColumnNames(self):
        return []

    def columnNames(self):
        if self.columnNames == None:
            rawColumnNames = self.rawColumnNames()
            nonAnonymousColumnNames = [name for name in rawColumnNames if not any(name == self.anonymousColumnNames())]
            if len(nonAnonymousColumnNames) != len(set(nonAnonymousColumnNames)):
                raise Exception('No duplicate columns, yo')
            if len(rawColumnNames) != len(set(rawColumnNames)):
                i = 0
                rawColumnNames = [i if any(name == self.anonymousColumnNames()) else name for i, name in rawColumnNames]
            self.columnNames = rawColumnNames
        return self.columnNames

    def current(self):
        if self.__current == None:
            # Some iterators may need to peek at a result to grab these,
            # make sure we invoke this prior to fetching the row
            # columnNames = self.columnNames
            # In Python we can't currently do that so uh... sorry?
            self.__current = self.fetchCurrent()
            if self.__current == None:
                raise StopIteration
            for transformation in self.transformations:
                if len(inspect.signature(transformation).parameters) == 1:
                    self.__current = transformation(self.__current)
                elif len(inspect.signature(transformation).parameters) == 2:
                    self.__current, self.index = transformation(self.__current, self.index)
        return self.__current

class Statement(AbstractIterator, psycopg2.extras.RealDictCursor):
    def __init__(self, *args, **kwargs):
        RealDictCursor.__init__(self, *args, **kwargs)
        AbstractIterator.__init__(self)

    def anonymousColumnNames(self):
        return ['?column?']

    # This currently forces a row load - I can't see the internals
    # to figure out how psycopg2 gets query result metadata (or at least)
    # I don't have time during a hackday
    def rawColumnNames(self):
        return self.current().keys()

    def fetchCurrent(self):
        return super().fetchone()

    def fetchone(self):
        return self.current()

    def fetchall(self):
        result = {}
        for key, value in self:
            result[key] = value
        return result
